<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFollowupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'opportunity_id' => ['nullable', 'exists:opportunities,id'],
            'title' => ['required', 'string', 'max:255'],
            'due_at' => ['required', 'date', 'after:now'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'owner_id' => ['sometimes', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return ['due_at.after' => 'Reminders must be set in the future — pick a date and time ahead of now (Asia/Manila).'];
    }
}
