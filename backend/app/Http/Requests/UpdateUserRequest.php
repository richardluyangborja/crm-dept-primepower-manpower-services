<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'team_id' => ['nullable', 'exists:teams,id'],
            'phone' => ['nullable', 'regex:/^\+63\d{10}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Phone must be PH format: +639XXXXXXXXX.'];
    }
}
