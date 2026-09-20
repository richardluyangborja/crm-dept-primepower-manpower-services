<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:teams,name'],
            'region' => ['nullable', 'string', 'max:100'],
        ];
    }
}
