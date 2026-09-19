<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFollowupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'due_at' => ['sometimes', 'date', 'after:now'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'owner_id' => ['sometimes', 'exists:users,id'],
        ];
    }
}
