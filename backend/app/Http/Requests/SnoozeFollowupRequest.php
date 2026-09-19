<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SnoozeFollowupRequest extends FormRequest
{
    public function rules(): array
    {
        return ['snoozed_until' => ['required', 'date', 'after:now']];
    }

    public function messages(): array
    {
        return ['snoozed_until.after' => 'Snooze to a future time — otherwise just mark it done.'];
    }
}
