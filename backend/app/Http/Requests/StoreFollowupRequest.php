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
            'due_at' => ['required', 'date', 'after:now'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'owner_id' => ['sometimes', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return ['due_at.after' => 'Reminders must be set in the future — pick a date and time ahead of now (Asia/Manila).'];
    }
}
