<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSurveyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'template_id' => ['required', 'exists:survey_templates,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'channel' => ['sometimes', 'in:link,email_mock,sms_mock'],
            'due_at' => ['sometimes', 'date', 'after:now'],
        ];
    }
}
