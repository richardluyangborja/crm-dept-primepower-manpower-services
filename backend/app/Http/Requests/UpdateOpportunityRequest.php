<?php

namespace App\Http\Requests;

use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpportunityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'value_centavos' => ['sometimes', 'integer', 'min:0'],
            'headcount' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'rate_per_head_centavos' => ['nullable', 'integer', 'min:0'],
            'contract_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'probability' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id' => ['sometimes', 'exists:users,id'],
            'stage' => ['prohibited'], // stage changes go through /move (audit + guards)
        ];
    }
}
