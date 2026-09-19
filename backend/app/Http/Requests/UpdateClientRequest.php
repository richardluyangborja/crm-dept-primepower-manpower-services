<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:100'],
            'size_band' => ['nullable', 'string', 'max:50'],
            'address_city' => ['nullable', 'string', 'max:100'],
            'address_province' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'regex:/^\+63\d{10}$/'],
            'status' => ['sometimes', 'in:prospect,active,inactive,blacklisted'],
            'source' => ['nullable', 'string', 'max:50'],
            'owner_id' => ['sometimes', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return ['contact_phone.regex' => 'Phone must be PH format: +639XXXXXXXXX.'];
    }
}
