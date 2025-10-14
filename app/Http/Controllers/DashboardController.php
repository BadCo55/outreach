<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $t0     = microtime(true);
        $reqId  = bin2hex(random_bytes(4));

        $page      = max(1, (int) $request->query('page', 1));
        $perPage   = (int) $request->query('perPage', 25);
        if (!in_array($perPage, [10, 25, 50, 100], true)) $perPage = 25;

        $olderMonths = (int) $request->query('olderMonths', 0);
        $search      = trim((string) $request->query('search', ''));
        $sortBy      = (string) $request->query('sortBy', 'date_raw');
        $sortDir     = strtolower((string) $request->query('sortDir', 'desc'));
        $realtorOnly = $request->boolean('realtorOnly', false);
        if (!in_array($sortBy, ['date_raw', 'last_name', 'fee'], true)) $sortBy = 'date_raw';
        if (!in_array($sortDir, ['asc', 'desc'], true)) $sortDir = 'desc';

        Log::info('dashboard:start', compact('reqId', 'page', 'perPage', 'olderMonths', 'search', 'sortBy', 'sortDir'));

        // 1) Read normalized rows from proxy-owned cache
        $rows = Cache::get('proxy:customer_latest:normalized');

        // ✅ Always make it a Collection before using ->filter()/->map()
        $rows = collect($rows ?? []);

        // Realtor-only filter (now safe)
        if ($realtorOnly) {
            $rows = $rows->filter(fn($r) => ($r['customer']['is_realtor'] ?? false) === true);
        }

        // If cache empty, warm and re-read
        if ($rows->isEmpty()) {
            Log::warning('dashboard:cache_miss', ['req_id' => $reqId]);

            try {
                Http::acceptJson()
                    ->connectTimeout(2)
                    ->timeout(4)
                    ->get(route('get-customers'), ['page' => 1, 'perPage' => 1]);
                Log::info('dashboard:warm_ping_sent', ['req_id' => $reqId]);
            } catch (\Throwable $e) {
                Log::warning('dashboard:warm_ping_failed', ['req_id' => $reqId, 'error' => $e->getMessage()]);
            }

            // Re-read and collect again
            $rows = collect(Cache::get('proxy:customer_latest:normalized', []));
            if ($realtorOnly && $rows->isNotEmpty()) {
                $rows = $rows->filter(fn($r) => ($r['customer']['is_realtor'] ?? false) === true);
            }
        }

        // 2) Derive months_since + fee_num for sort/filter
        $today = Carbon::today();
        $rows = $rows->map(function ($r) use ($today) {
            $iso = $r['inspection']['date_raw'] ?? null;
            $months = null;
            if ($iso) {
                try {
                    $months = Carbon::parse($iso)->diffInMonths($today);
                } catch (\Throwable $e) {
                    $months = null;
                }
            }
            $r['inspection']['months_since'] = $months;
            $r['inspection']['fee_num'] = isset($r['inspection']['fee'])
                ? (float) str_replace([',', '$'], '', $r['inspection']['fee'])
                : null;
            return $r;
        });

        // 2) Derive months_since + fee_num for sort/filter
        $today = Carbon::today();
        $rows = $rows->map(function ($r) use ($today) {
            $iso = $r['inspection']['date_raw'] ?? null;     // make sure proxy added date_raw
            $months = null;
            if ($iso) {
                try {
                    $months = Carbon::parse($iso)->diffInMonths($today);
                } catch (\Throwable $e) {
                    $months = null;
                }
            }
            $r['inspection']['months_since'] = $months;
            $r['inspection']['fee_num'] = isset($r['inspection']['fee'])
                ? (float) str_replace([',', '$'], '', $r['inspection']['fee'])
                : null;
            return $r;
        });

        // 3) Filters
        if ($olderMonths > 0) {
            $rows = $rows->filter(
                fn($r) =>
                isset($r['inspection']['months_since']) &&
                    $r['inspection']['months_since'] >= $olderMonths
            );
        }

        if ($search !== '') {
            $q = Str::lower($search);
            $rows = $rows->filter(function ($r) use ($q) {
                $hay = Str::lower(implode(' ', [
                    $r['customer']['first_name'] ?? '',
                    $r['customer']['last_name'] ?? '',
                    $r['customer']['email_1'] ?? '',
                    $r['customer']['email_2'] ?? '',
                    $r['customer']['phone_1'] ?? '',
                    $r['customer']['phone_2'] ?? '',
                    $r['property']['street_address'] ?? '',
                    $r['property']['city'] ?? '',
                ]));
                return Str::contains($hay, $q);
            });
        }

        // 4) Sort
        $rows = $rows->sortBy(function ($r) use ($sortBy) {
            return match ($sortBy) {
                'last_name' => $r['customer']['last_name'] ?? '',
                'fee'       => $r['inspection']['fee_num'] ?? 0,
                default     => $r['inspection']['date_raw'] ?? '1900-01-01',
            };
        }, SORT_REGULAR, $sortDir === 'desc')->values();

        // 5) Paginate
        $total  = $rows->count();
        $offset = ($page - 1) * $perPage;
        $paged  = $rows->slice($offset, $perPage)->values();

        $meta = [
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => (int) ceil(max(1, $total) / max(1, $perPage)),
            'from'     => $total ? $offset + 1 : 0,
            'to'       => $total ? $offset + $paged->count() : 0,
            'source'   => 'cache',
            'olderMonths' => $olderMonths,
            'search'      => $search,
            'sortBy'      => $sortBy,
            'sortDir'     => $sortDir,
        ];

        Log::info('dashboard:render', [
            'req_id'   => $reqId,
            'rows'     => $paged->count(),
            'meta'     => $meta,
            'ms_total' => round((microtime(true) - $t0) * 1000),
        ]);

        return Inertia::render('Dashboard', [
            'rows' => $paged,
            'meta' => $meta,
        ]);
    }
}
