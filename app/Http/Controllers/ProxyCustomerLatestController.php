<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ProxyCustomerLatestController extends Controller
{
    public function __invoke(Request $request)
    {
        $t0     = microtime(true);
        $reqId  = bin2hex(random_bytes(4)); // small request id for correlation
        $url    = config('services.old_portal.customer_latest');

        // pagination inputs
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('perPage', 25);
        if (!in_array($perPage, [10, 25, 50, 100], true)) $perPage = 25;

        Log::info('proxy:start', [
            'req_id'  => $reqId,
            'url'     => $url,
            'page'    => $page,
            'perPage' => $perPage,
        ]);

        // 1) Serve cached normalized data immediately if present
        $cachedRows = Cache::get('proxy:customer_latest:normalized');
        if ($cachedRows) {
            $rows   = collect($cachedRows);

            $rows = $this->rejectExistingCustomers($rows);

            $total  = $rows->count();
            $offset = ($page - 1) * $perPage;
            $paged  = $rows->slice($offset, $perPage)->values();

            Log::info('proxy:cache_hit', [
                'req_id' => $reqId,
                'total'  => $total,
                'offset' => $offset,
                'count'  => $paged->count(),
                'first_sample' => $paged->first() ? [
                    'customer'   => $paged[0]['customer']['id'] ?? null,
                    'inspection' => $paged[0]['inspection']['id'] ?? null,
                ] : null,
            ]);

            // Fire-and-forget quick refresh (ignore result)
            try {
                Http::acceptJson()->connectTimeout(2)->timeout(5)->get($url);
                Log::info('proxy:bg_refresh_ping', ['req_id' => $reqId, 'status' => 'sent']);
            } catch (\Throwable $e) {
                Log::warning('proxy:bg_refresh_failed', ['req_id' => $reqId, 'error' => $e->getMessage()]);
            }

            $elapsed = round((microtime(true) - $t0) * 1000);
            Log::info('proxy:done', ['req_id' => $reqId, 'source' => 'cache', 'ms' => $elapsed]);

            return response()->json([
                'data' => $paged,
                'meta' => [
                    'total'    => $total,
                    'page'     => $page,
                    'perPage'  => $perPage,
                    'lastPage' => (int) ceil(max(1, $total) / max(1, $perPage)),
                    'from'     => $total ? $offset + 1 : 0,
                    'to'       => $total ? $offset + $paged->count() : 0,
                    'source'   => 'cache',
                ],
            ]);
        }

        Log::info('proxy:cache_miss', ['req_id' => $reqId]);

        // 2) No cache → call upstream
        $tu0 = microtime(true);
        try {
            $resp = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(8)
                ->retry(2, 300)
                ->get($url);

            $upMs = round((microtime(true) - $tu0) * 1000);
            Log::info('proxy:upstream_response', [
                'req_id' => $reqId,
                'status' => $resp->status(),
                'ms'     => $upMs,
            ]);
        } catch (\Throwable $e) {
            $upMs = round((microtime(true) - $tu0) * 1000);
            Log::error('proxy:upstream_exception', [
                'req_id' => $reqId,
                'ms'     => $upMs,
                'error'  => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Upstream request failed'], 502);
        }

        if (!$resp->ok()) {
            Log::warning('proxy:upstream_not_ok', [
                'req_id' => $reqId,
                'status' => $resp->status(),
                'body_len' => strlen((string) $resp->body()),
            ]);
            return response()->json(['error' => 'Upstream request failed'], 502);
        }

        $raw  = $resp->json();
        $rows = $this->normalize($raw)->values();

        $rows = $this->rejectExistingCustomers($rows);

        // Cache normalized for quick future loads
        Cache::put('proxy:customer_latest:normalized', $rows, now()->addMinutes(1440));
        Log::info('proxy:normalized_cached', [
            'req_id' => $reqId,
            'normalized_count' => $rows->count(),
        ]);

        // paginate normalized
        $total  = $rows->count();
        $offset = ($page - 1) * $perPage;
        $paged  = $rows->slice($offset, $perPage)->values();

        Log::info('proxy:paginate', [
            'req_id' => $reqId,
            'total'  => $total,
            'offset' => $offset,
            'count'  => $paged->count(),
            'first_sample' => $paged->first() ? [
                'customer'   => $paged[0]['customer']['id'] ?? null,
                'inspection' => $paged[0]['inspection']['id'] ?? null,
            ] : null,
        ]);

        $elapsed = round((microtime(true) - $t0) * 1000);
        Log::info('proxy:done', ['req_id' => $reqId, 'source' => 'live', 'ms' => $elapsed]);

        return response()->json([
            'data' => $paged,
            'meta' => [
                'total'    => $total,
                'page'     => $page,
                'perPage'  => $perPage,
                'lastPage' => (int) ceil(max(1, $total) / max(1, $perPage)),
                'from'     => $total ? $offset + 1 : 0,
                'to'       => $total ? $offset + $paged->count() : 0,
                'source'   => 'live',
            ],
        ]);
    }

    private function normalize(array $data): Collection
    {
        return collect($data)
            ->map(function ($item) {
                // helpers
                $toBool = fn($v) => $v === null ? null : (int)$v === 1;

                $formatDate = function ($date) {
                    if (!$date) return null;
                    $ts = strtotime((string)$date);
                    return $ts ? date('m/d/y', $ts) : null;
                };

                $toIso = function ($date) {
                    if (!$date) return null;
                    $ts = strtotime((string)$date);
                    return $ts ? date('Y-m-d', $ts) : null;
                };

                $formatCurrency = function ($value) {
                    if ($value === null || $value === '') return null;
                    return '$' . number_format((float)$value, 2);
                };

                $formatPhone = function ($value) {
                    if (!$value) return null;
                    $digits = preg_replace('/\D/', '', $value);
                    if (strlen($digits) === 10) {
                        return sprintf(
                            '(%s) %s-%s',
                            substr($digits, 0, 3),
                            substr($digits, 3, 3),
                            substr($digits, 6)
                        );
                    }
                    if (strlen($digits) === 11 && $digits[0] === '1') {
                        return sprintf(
                            '+1 (%s) %s-%s',
                            substr($digits, 1, 3),
                            substr($digits, 4, 3),
                            substr($digits, 7)
                        );
                    }
                    return $value;
                };

                $propertyTypes = [
                    1 => 'Single-Family Home',
                    2 => 'Multi-Unit Building',
                    3 => 'Townhouse/Villa',
                    4 => 'Condominium',
                    5 => 'Commercial Building',
                ];

                $roles = [
                    'primary_customer' => 'Primary Customer',
                    'secondary_customer' => 'Secondary Customer',
                    'primary_agent' => 'Primary Agent',
                    'secondary_agent' => 'Secondary Agent',
                ];

                return [
                    'customer' => [
                        'id'            => isset($item['customer_id']) ? (int)$item['customer_id'] : null,
                        'first_name'    => $item['first_name'] ?? null,
                        'last_name'     => $item['last_name'] ?? null,
                        'phone_1'       => $formatPhone($item['phone_number'] ?? null),
                        'phone_2'       => $formatPhone($item['phone_number_2'] ?? null),
                        'email_1'       => strtolower($item['email']) ?? null,
                        'email_2'       => strtolower($item['email_2']) ?? null,
                        'is_realtor'    => $toBool($item['is_realtor'] ?? null),
                    ],
                    'inspection' => [
                        'id'            => isset($item['inspection_number']) ? (int)$item['inspection_number'] : null,
                        'date'          => $formatDate($item['inspection_date'] ?? null),
                        'date_raw'      => $toIso($item['inspection_date'] ?? null),
                        'fee'           => $formatCurrency($item['total_fee'] ?? null),
                        'general'       => $toBool($item['general_inspection'] ?? null),
                        'mitigation'    => $toBool($item['mitigation'] ?? null),
                        'four_point'    => $toBool($item['four_point'] ?? null),
                        'customer_role' => $roles[$item['customer_role']] ?? null,
                    ],
                    'property' => [
                        'id'                => isset($item['property_id']) ? (int)$item['property_id'] : null,
                        'property_type'     => isset($item['property_type_id']) ? $propertyTypes[(int)$item['property_type_id']] : null,
                        'street_address'    => $item['street_address'] ?? null,
                        'city'              => $item['city'] ?? null,
                        'state'             => $item['state'] ?? 'FL',
                        'square_footage'    => isset($item['square_footage']) ? number_format((float)$item['square_footage']) : null,
                    ],
                ];
            })
            ->filter(function ($record) {
                $cust = $record['customer'];
                $insp = $record['inspection'];

                // Must have at least one contact method
                $hasContact = $cust['phone_1'] || $cust['phone_2'] || $cust['email_1'] || $cust['email_2'];

                // Must have an inspection id/number
                $hasInspection = !empty($insp['id']);

                return $hasContact && $hasInspection;
            })
            ->values();
    }

    private function rejectExistingCustomers(Collection $rows): Collection
    {
        // Collect legacy IDs from the normalized payload
        $ids = $rows
            ->pluck('customer.id')
            ->filter(fn($v) => $v !== null)
            ->map(fn($v) => (int)$v)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $rows;
        }

        // Fetch existing legacy IDs from your DB
        $existing = Customer::query()
            ->whereIn('legacy_id', $ids)
            ->pluck('legacy_id')
            ->map(fn($v) => (int)$v)
            ->all();

        // Drop rows whose legacy id already exists
        return $rows->reject(function ($row) use ($existing) {
            $legacyId = isset($row['customer']['id']) ? (int)$row['customer']['id'] : null;
            return $legacyId !== null && in_array($legacyId, $existing, true);
        })->values();
    }
}
