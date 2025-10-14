<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Customer;
use App\Models\ContactRecord;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreContactRecordRequest;
use App\Http\Requests\UpdateContactRecordRequest;

class ContactRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContactRecordRequest $request, Customer $customer)
    {
        $data = $request->validated();
        $occurredAt = Carbon::parse($data['date'] . ' ' . $data['time']);

        ContactRecord::create([
            'customer_id'       => $customer->id,
            'user_id'           => Auth::id(),
            'contact_type'      => $data['contact_type'],
            'call_outcome'      => $data['call_outcome'] ?? null,
            'call_direction'    => $data['call_direction'] ?? null,
            'occurred_at'       => $occurredAt,
            'notes'             => $data['notes'] ?? null,
            'meta'              => [],
        ]);

        $customer->last_contact_at      = $occurredAt;
        $customer->last_contact_type    = $data['contact_type'];
        $customer->save();

        return back()->with('success', 'Contact logged.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ContactRecord $contactRecord)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ContactRecord $contactRecord)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContactRecordRequest $request, ContactRecord $contactRecord)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ContactRecord $contactRecord)
    {
        //
    }
}
