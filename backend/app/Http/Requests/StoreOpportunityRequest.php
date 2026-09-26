<?php

namespace App\Http\Requests;

use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpportunityRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_id' => \App\Models\Client::decodeId($this->input('client_id')) ?? $this->input('client_id'),
            'company_id' => \App\Models\Company::decodeId($this->input('company_id')) ?? $this->input('company_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'exists:clients,id'],
            'company_id' => ['nullable', 'required_without:client_id', 'exists:companies,id'],
            'title' => ['required', 'string', 'max:255'],
            'stage' => ['sometimes', Rule::in(Opportunity::STAGES)],
            'value_centavos' => ['required', 'integer', 'min:1'],
            'headcount' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'rate_per_head_centavos' => ['nullable', 'integer', 'min:0'],
            'contract_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'probability' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date', 'after_or_equal:today'],
            'owner_id' => ['sometimes', Rule::exists('users', 'id')->where(fn ($q) => $q->whereIn('role', ['sales_rep', 'manager'])->where('is_active', true))],
        ];
    }
}
