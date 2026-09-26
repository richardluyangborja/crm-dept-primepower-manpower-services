<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFollowupRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_id' => \App\Models\Client::decodeId($this->input('client_id')) ?? $this->input('client_id'),
            'opportunity_id' => \App\Models\Opportunity::decodeId($this->input('opportunity_id')) ?? $this->input('opportunity_id'),
            'company_id' => \App\Models\Company::decodeId($this->input('company_id')) ?? $this->input('company_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'exists:clients,id'],
            'company_id' => ['nullable', 'required_without:client_id', 'exists:companies,id'],
            'opportunity_id' => ['nullable', 'exists:opportunities,id'],
            'title' => ['required', 'string', 'max:255'],
            'due_at' => ['required', 'date', 'after_or_equal:today'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'owner_id' => ['sometimes', \Illuminate\Validation\Rule::exists('users', 'id')->where(fn ($q) => $q->whereIn('role', ['sales_rep', 'manager'])->where('is_active', true))], // assign to an active rep/manager
        ];
    }

    public function messages(): array
    {
        return ['due_at.after_or_equal' => 'Reminders can be due today or later — pick a date from today on (Asia/Manila).'];
    }
}
