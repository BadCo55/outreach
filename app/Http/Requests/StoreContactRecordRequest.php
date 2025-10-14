<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contact_type'      => ['required', 'in:phone_call,text_message,email,mail'],
            'call_outcome'      => ['nullable', 'in:busy,connected,left_voicemail,no_answer,wrong_number'],
            'call_direction'    => ['nullable', 'in:inbound,outbound'],
            'date'              => ['required', 'date'],
            'time'              => ['required', 'date_format:H:i'],
            'notes'             => ['nullable', 'string', 'max:2000'],
        ];
    }
}
