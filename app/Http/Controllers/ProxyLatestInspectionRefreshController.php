<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ProxyLatestInspectionRefreshController extends Controller
{
    /**
     * Refresh the latest inspection data for a given customer.
     *
     * Expected request payload:
     * {
     *     "customer_id": 123,
     *     "legacy_id": 456
     * }
     */
    public function __invoke(Request $request)
    {
        // Correlation ID for these logs
        $reqId = Str::uuid()->toString();

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'legacy_id'   => ['required', 'integer'],
        ]);

        $customerId = (int) $validated['customer_id'];
        $legacyId   = (int) $validated['legacy_id'];

        Log::info('latestInspection:start', [
            'req_id'      => $reqId,
            'customer_id' => $customerId,
            'legacy_id'   => $legacyId,
        ]);

        $customer = Customer::findOrFail($customerId);

        $url = 'https://app.dhi-portal.net/customers/refreshLatestInspection';

        $response = Http::acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->retry(2, 250)
            ->get($url, [
                'legacy_customer_id' => $legacyId,
            ]);

        $payload    = $response->json();        // <-- array already
        $normalized = $this->normalize($payload)->first();  // one record

        $latestInspection = $normalized['inspection'];
        $latestInspection['property'] = $normalized['property'];

        $customer->update([
            'latest_inspection' => $latestInspection
        ]);

        

    }

    /**
     * Normalize various possible legacy response shapes into a consistent array.
     * Accepts:
     *  - { success: true, data: {...} }
     *  - { success: true, data: [ {...} ] }
     *  - [ {...} ]
     *  - {...}
     * Returns null if nothing usable is found.
     */
    private function normalize(array $data): \Illuminate\Support\Collection
    {
        // If we got a single associative record, wrap it as a list
        $items = $this->isAssoc($data) ? [$data] : $data;

        return collect($items)
            ->map(function ($item) {
                // helpers
                $toBool = fn($v) => $v === null ? null : ((int)$v === 1);

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
                        return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
                    }
                    if (strlen($digits) === 11 && $digits[0] === '1') {
                        return sprintf('+1 (%s) %s-%s', substr($digits, 1, 3), substr($digits, 4, 3), substr($digits, 7));
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
                    'primary_customer'   => 'Primary Customer',
                    'secondary_customer' => 'Secondary Customer',
                    'primary_agent'      => 'Primary Agent',
                    'secondary_agent'    => 'Secondary Agent',
                ];

                return [
                    'customer' => [
                        'id'         => isset($item['customer_id']) ? (int)$item['customer_id'] : null,
                        'first_name' => $item['first_name'] ?? null,
                        'last_name'  => $item['last_name'] ?? null,
                        'phone_1'    => $formatPhone($item['phone_number'] ?? null),
                        'phone_2'    => $formatPhone($item['phone_number_2'] ?? null),
                        // be defensive with strtolower when key might be missing/null
                        'email_1'    => isset($item['email'])   ? strtolower((string)$item['email'])   : null,
                        'email_2'    => isset($item['email_2']) ? strtolower((string)$item['email_2']) : null,
                        'is_realtor' => $toBool($item['is_realtor'] ?? null),
                    ],
                    'inspection' => [
                        'id'            => isset($item['inspection_number']) ? (int)$item['inspection_number'] : null,
                        'date'          => $formatDate($item['inspection_date'] ?? null),
                        'date_raw'      => $toIso($item['inspection_date'] ?? null),
                        'fee'           => $formatCurrency($item['total_fee'] ?? null),
                        'general'       => $toBool($item['general_inspection'] ?? null),
                        'mitigation'    => $toBool($item['mitigation'] ?? null),
                        'four_point'    => $toBool($item['four_point'] ?? null),
                        'customer_role' => isset($item['customer_role']) ? ($roles[$item['customer_role']] ?? $item['customer_role']) : null,
                    ],
                    'property' => [
                        'id'             => isset($item['property_id']) ? (int)$item['property_id'] : null,
                        'property_type'  => isset($item['property_type_id']) ? ($propertyTypes[(int)$item['property_type_id']] ?? null) : null,
                        'street_address' => $item['street_address'] ?? null,
                        'city'           => $item['city'] ?? null,
                        'state'          => $item['state'] ?? 'FL',
                        'square_footage' => isset($item['square_footage']) ? number_format((float)$item['square_footage']) : null,
                    ],
                ];
            })
            ->filter(function ($record) {
                $cust = $record['customer'];
                $insp = $record['inspection'];

                $hasContact    = $cust['phone_1'] || $cust['phone_2'] || $cust['email_1'] || $cust['email_2'];
                $hasInspection = !empty($insp['id']);

                return $hasContact && $hasInspection;
            })
            ->values();
    }

    private function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
