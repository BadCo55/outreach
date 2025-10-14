<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation()
    {
        $data = $this->all();

        if (isset($data['customer'])) {
            // Normalize emails & phone
            if (isset($data['customer']['email_1'])) {
                $data['customer']['email_1'] = Str::lower(trim((string) $data['customer']['email_1']));
            }
            if (isset($data['customer']['email_2'])) {
                $data['customer']['email_2'] = Str::lower(trim((string) $data['customer']['email_2']));
            }

            // Strip non-digits from phones
            foreach (['phone_1', 'phone_2'] as $k) {
                if (!empty($data['customer'][$k])) {
                    $digits = preg_replace('/\D+/', '', (string) $data['customer'][$k]);
                    $data['customer'][$k] = $digits;
                }
            }

            // Coerce is_realtor into boolean
            if (array_key_exists('is_realtor', $data['customer'])) {
                $data['customer']['is_realtor'] = filter_var($data['customer']['is_realtor'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }
        }

        // Normalize property.square_footage to integer when possible (e.g., "1,234" -> 1234)
        if (isset($data['property']['square_footage']) && $data['property']['square_footage'] !== null && $data['property']['square_footage'] !== '') {
            $data['property']['square_footage'] = (int) str_replace(',', '', (string) $data['property']['square_footage']);
        }

        // Normalize inspection.fee to a clean string without $ or commas for easier parsing server-side
        if (isset($data['inspection']['fee']) && $data['inspection']['fee'] !== null && $data['inspection']['fee'] !== '') {
            $clean = str_replace([',', '$', ' '], '', (string) $data['inspection']['fee']); // e.g. "$1,234.50" -> "1234.50"
            $data['inspection']['fee'] = $clean;
        }

        // Push normalized data back
        $this->replace($data);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token'               => ['required', 'string', 'max:100'],
            'legacy_customer_id'  => ['required', 'integer', 'min:1'],

            // Customer (required)
            'customer'                        => ['required', 'array'],
            'customer.first_name'             => ['required', 'string', 'max:100'],
            'customer.last_name'              => ['required', 'string', 'max:100'],
            'customer.email_1'                => ['nullable', 'email', 'max:255'],
            'customer.email_2'                => ['nullable', 'email', 'max:255'],
            // store digits in prepareForValidation; min 10 digits is typical NANP
            'customer.phone_1'                => ['nullable', 'regex:/^\d{7,15}$/'],
            'customer.phone_2'                => ['nullable', 'regex:/^\d{7,15}$/'],
            'customer.is_realtor'             => ['required', 'boolean'],

            // Property (required object, fields can be null per your form)
            'property'                        => ['required', 'array'],
            'property.id'                     => ['nullable', 'integer', 'min:1'],
            'property.property_type'          => ['nullable', 'string', 'max:100'],
            'property.street_address'         => ['nullable', 'string', 'max:255'],
            'property.city'                   => ['nullable', 'string', 'max:120'],
            'property.state'                  => ['nullable', 'string', 'max:2'],
            'property.square_footage'         => ['nullable', 'integer', 'min:0'],

            // Inspection (required object, fields can be null per your form)
            'inspection'                      => ['required', 'array'],
            'inspection.id'                   => ['nullable', 'integer', 'min:1'],
            'inspection.customer_role'        => ['nullable', 'string', 'max:100'],
            'inspection.date'                 => ['nullable', 'date_format:m/d/y'],
            'inspection.date_raw'             => ['nullable', 'date'], // ISO-like
            // you normalized to "1234.56" string in prepareForValidation; validate numeric string
            'inspection.fee'                  => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'inspection.general'              => ['nullable', 'boolean'],
            'inspection.mitigation'           => ['nullable', 'boolean'],
            'inspection.four_point'           => ['nullable', 'boolean'],

            // Contact Records
            'contact_records'                 => ['nullable', 'array'],
            'contact_records.*.contact_type'  => ['required', Rule::in(['phone_call','text_message','email','mail'])],
            'contact_records.*.call_outcome'  => ['nullable', Rule::in(['busy','connected','left_voicemail','no_answer','wrong_number'])],
            'contact_records.*.call_direction'=> ['nullable', Rule::in(['inbound','outbound'])],
            'contact_records.*.date'          => ['required_with:contact_records.*.time', 'date'],           // "YYYY-MM-DD"
            'contact_records.*.time'          => ['required_with:contact_records.*.date', 'date_format:H:i'], // "HH:MM"
            'contact_records.*.notes'         => ['nullable', 'string', 'max:2000'],
        ];
    }
}
