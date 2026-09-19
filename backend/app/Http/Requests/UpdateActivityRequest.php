<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActivityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'outcome' => ['nullable', 'string', 'max:50'],
            'occurred_at' => ['sometimes', 'date', 'before_or_equal:now'],
        ];
    }
}
