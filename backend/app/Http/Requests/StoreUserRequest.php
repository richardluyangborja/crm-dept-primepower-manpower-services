<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /** Creatable roles by actor (specs/02): superadmin → anyone but superadmin; admin → manager/sales_rep only. */
    public static function creatableRoles(?string $actorRole): array
    {
        return $actorRole === 'superadmin'
            ? ['admin', 'manager', 'sales_rep']
            : ['manager', 'sales_rep'];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10'],
            'role' => ['required', Rule::in(self::creatableRoles($this->user()?->role))],
            'team_id' => ['required_if:role,manager,sales_rep', 'nullable', 'exists:teams,id'],
            'phone' => ['nullable', 'regex:/^\+63\d{10}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'Temporary passwords must be at least 10 characters.',
            'team_id.required_if' => 'Managers and sales reps must belong to a team.',
            'phone.regex' => 'Phone must be PH format: +639XXXXXXXXX.',
        ];
    }
}
