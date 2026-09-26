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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_id' => \App\Models\Company::decodeId($this->input('company_id')) ?? $this->input('company_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            // Existing company (opaque id) or inline new-company payload — one is required.
            'company_id' => ['nullable', 'exists:companies,id'],
            'company' => ['nullable', 'array', 'required_without:company_id'],
            'company.name' => ['required_with:company', 'string', 'max:255'],
            'company.industry' => ['nullable', 'string', 'max:100'],
            'company.address_city' => ['nullable', 'string', 'max:100'],
            'company.address_province' => ['nullable', 'string', 'max:100'],
            'company.contact_email' => ['nullable', 'email', 'max:255'],
            'company.contact_phone' => ['nullable', 'regex:/^\+63\d{10}$/'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_position' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'regex:/^\+63\d{10}$/'],
            'headcount_needed' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'positions' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', Rule::in(Lead::SOURCES)],
            'status' => ['sometimes', Rule::in(Lead::STATUSES)],
            'notes' => ['nullable', 'string'],
            'owner_id' => ['sometimes', Rule::exists('users', 'id')->where(fn ($q) => $q->whereIn('role', ['sales_rep', 'manager'])->where('is_active', true))], // assign to an active rep/manager; rep forced to self
        ];
    }

    public function messages(): array
    {
        return ['contact_phone.regex' => 'Phone must be PH format: +639XXXXXXXXX.'];
    }
}
