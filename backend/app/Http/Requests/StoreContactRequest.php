<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'regex:/^\+63\d{10}$/'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Phone must be PH format: +639XXXXXXXXX.'];
    }
}
