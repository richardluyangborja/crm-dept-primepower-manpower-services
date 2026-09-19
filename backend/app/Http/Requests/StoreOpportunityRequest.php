<?php

namespace App\Http\Requests;

use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpportunityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'stage' => ['sometimes', Rule::in(Opportunity::STAGES)],
            'value_centavos' => ['sometimes', 'integer', 'min:0'],
            'probability' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date', 'after_or_equal:today'],
            'owner_id' => ['sometimes', 'exists:users,id'],
        ];
    }
}
