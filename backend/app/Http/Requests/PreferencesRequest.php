<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreferencesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'theme' => ['sometimes', 'in:light,dark,system'],
            'sync_system' => ['sometimes', 'boolean'],
            'notifications' => ['sometimes', 'array'],
            'notifications.reminder_due' => ['sometimes', 'boolean'],
            'notifications.overdue' => ['sometimes', 'boolean'],
            'notifications.escalation' => ['sometimes', 'boolean'],
            'notifications.survey_response' => ['sometimes', 'boolean'],
            'notifications.assignment' => ['sometimes', 'boolean'],
            'quiet_hours_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['sometimes', 'nullable', 'date_format:H:i'],
        ];
    }
}
