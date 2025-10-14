<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Customer;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ContactRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use Carbon\Carbon;

class CustomerController extends Controller
{
    public function start(Request $request, int $legacyId)
    {
        Log::info('request received at start', [
            'request' => $request->all(),
            'legacy_id' => $legacyId,
        ]);

        $rows = collect(Cache::get('proxy:customer_latest:normalized', []));
        $row = $rows->firstWhere('customer.id', (int)$legacyId);

        Log::info('row', [
            'rows' => $rows->first(),
            'row' => $row,
        ]);

        // Optional warm if cache empty
        if (!$row && $rows->isEmpty()) {
            try {
                Http::acceptJson()->connectTimeout(2)->timeout(4)->get(route('get-customers'), ['page' => 1, 'perPage' => 1]);
            } catch (\Throwable $e) {
            }
            $rows = collect(Cache::get('proxy:customer_latest:normalized', []));
            $row = $rows->first(fn($r) => (int)($r['customer']['id'] ?? 0) === $legacyId);
        }

        if (!$row) abort(404, 'Customer not found in source data.');

        // Create a token and stash the row
        $token = Str::random(20);
        Cache::put("intake:$token", $row, now()->addMinutes(30));

        // Redirect to create page with token (deep-linkable)
        return redirect()->route('customer.create', ['token' => $token]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage    = (int)($request->integer('per_page') ?: 10);
        $page       = (int)($request->integer('page') ?: 1);

        $sortable = [
            'last_name',
            'phone_1',
            'email_1',
            'is_realtor',
            'last_contact_at',
            'created_at',
            'updated_at',
        ];

        $requestedSortField = trim((string)$request->get('sort_field', ''));

        $sortField = in_array($requestedSortField, $sortable, true) ? $requestedSortField : 'created_at';
        $sortOrderRaw = $request->get('sort_order', -1);
        $sortOrder    = is_numeric($sortOrderRaw) ? (int)$sortOrderRaw : ($sortOrderRaw === 'asc' ? 1 : -1);
        $direction    = $sortOrder === 1 ? 'asc' : 'desc';

        $search     = trim((string)$request->get('search', ''));

        $query      = Customer::query()
                        ->when($search !== '', function ($q) use ($search) {
                            $q->where(function ($q) use ($search) {
                                $q  ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('email_1', 'like', "%{$search}%")
                                    ->orWhere('phone_1', 'like', "%{$search}%");
                            });
                        });



        $customers = $query
            ->orderBy($sortField, $direction)
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();


        return Inertia::render('customer/Index', [
            'customers' => $customers,
            'filters'   => [
                'search'        => $search,
                'per_page'      => $perPage,
                'sort_field'    => $sortField,
                'sort_order'    => $sortOrder
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $token = (string) $request->query('token', '');
        $row = $token ? Cache::get("intake:$token") : null;

        if (!$row) {
            // You can render an empty form or show a friendly message
            return Inertia::render('customer/Create', [
                'token' => null,
                'initial' => null,
                'message' => 'Session expired. Please start intake again.',
            ]);
        }

        return Inertia::render('customer/Create', [
            'token' => $token,
            'initial' => $row, // normalized data from proxy cache
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();

        Log::info('Request received', ['data' => $data]);

        // Package the latest inspection (include property snapshot)
        $latestInspection = $data['inspection'] ?? [];
        $latestInspection['property'] = $data['property'] ?? null;

        try {
            $customer = DB::transaction(function () use ($data, $latestInspection) {
                // Match by legacy id (stable)
                $customer = Customer::updateOrCreate(
                    ['legacy_id' => $data['legacy_customer_id']],
                    [
                        'first_name'         => $data['customer']['first_name'],
                        'last_name'          => $data['customer']['last_name'],
                        'phone_1'            => $data['customer']['phone_1'] ?? null, // already digits in FormRequest
                        'phone_2'            => $data['customer']['phone_2'] ?? null,
                        'email_1'            => $data['customer']['email_1'] ?? null,
                        'email_2'            => $data['customer']['email_2'] ?? null,
                        'social_media_links' => [], // or keep existing with $customer->social_media_links ?? []
                        'is_realtor'         => (bool) $data['customer']['is_realtor'],
                        'latest_inspection'  => $latestInspection,
                    ]
                );

                // Create contact records if provided
                foreach ($data['contact_records'] ?? [] as $r) {
                    // Combine date + time to one occurred_at
                    $occurredAt = \Carbon\Carbon::parse(($r['date'] ?? '') . ' ' . ($r['time'] ?? '00:00'));

                    ContactRecord::create([
                        'customer_id'    => $customer->id,
                        'user_id'        => Auth::id(),
                        'contact_type'   => $r['contact_type'],
                        'call_outcome'   => $r['call_outcome'] ?? null,
                        'call_direction' => $r['call_direction'] ?? null,
                        'occurred_at'    => $occurredAt,
                        'notes'          => $r['notes'] ?? null,
                        'meta'           => [],
                    ]);
                }

                // Update cached summary on customer
                $last = $customer->contactRecords()->latest('occurred_at')->first();
                if ($last) {
                    $customer->last_contact_at   = $last->occurred_at;
                    $customer->last_contact_type = $last->contact_type;
                    $customer->save();
                }

                return $customer;
            });

            return redirect()
                ->route('customer.show', $customer->id)
                ->with('success', 'Customer saved.');
        } catch (\Throwable $th) {
            Log::error('Error creating customer', ['error' => $th->getMessage(), 'payload' => $request->all()]);
            return back()->with('error', 'There was an error creating the customer. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        $records = $customer->contactRecords()
            ->latest('occurred_at')
            ->paginate(5)
            ->through(function ($r) {
                return [
                    'id'                => $r->id,
                    'contact_type'      => $r->contact_type,
                    'call_outcome'      => $r->call_outcome,
                    'call_direction'    => $r->call_direction,
                    'occurred_at'       => $r->occurred_at?->toIso8601String(),
                    'notes'             => $r->notes,
                ];
            });

        return Inertia::render('customer/Show', [
            'customer' => $customer->only([
                'id','legacy_id','full_name','first_name','last_name',
                'phone_1','phone_2','email_1','email_2','social_media_links',
                'is_realtor','latest_inspection','created_at','updated_at',
                'last_contact_at','last_contact_type',
            ]),
            'contacts' => $records,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $data = $request->validate([
            'social_media_links'    => ['nullable', 'array'],
            'social_media_links.*'  => ['nullable', 'url'],
        ]);

        $customer->update([
            'social_media_links'    => $data['social_media_links'] ?? null,
        ]);

        return back()->with('success', 'Customer updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        //
    }
}
