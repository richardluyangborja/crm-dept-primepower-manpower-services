<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Step 3 of the convert wizard: optionally open an opportunity (Agent B owns opps, same shape). */
class ConvertLeadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client' => ['sometimes', 'array'],
            'client.name' => ['sometimes', 'string', 'max:255'],
            'client.industry' => ['nullable', 'string', 'max:100'],
            'client.address_city' => ['nullable', 'string', 'max:100'],
            'client.address_province' => ['nullable', 'string', 'max:100'],
            'create_opportunity' => ['sometimes', 'boolean'],
            'opportunity_title' => ['required_if:create_opportunity,true', 'nullable', 'string', 'max:255'],
            'opportunity_value_centavos' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
