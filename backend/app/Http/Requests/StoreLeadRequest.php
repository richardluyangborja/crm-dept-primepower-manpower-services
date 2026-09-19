<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // scoped in controller/policy; any authenticated role may capture
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'regex:/^\+63\d{10}$/'],
            'source' => ['nullable', Rule::in(Lead::SOURCES)],
            'status' => ['sometimes', Rule::in(Lead::STATUSES)],
            'notes' => ['nullable', 'string'],
            'owner_id' => ['sometimes', 'exists:users,id'], // admin/manager assign; rep forced to self
        ];
    }

    public function messages(): array
    {
        return ['contact_phone.regex' => 'Phone must be PH format: +639XXXXXXXXX.'];
    }
}
