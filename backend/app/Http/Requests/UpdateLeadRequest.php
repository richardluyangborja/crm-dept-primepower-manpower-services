<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'regex:/^\+63\d{10}$/'],
            'source' => ['nullable', Rule::in(Lead::SOURCES)],
            'status' => ['sometimes', Rule::in(Lead::STATUSES)],
            'unqualified_reason' => ['required_if:status,unqualified', 'nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'owner_id' => ['sometimes', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return ['contact_phone.regex' => 'Phone must be PH format: +639XXXXXXXXX.'];
    }
}
