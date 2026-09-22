<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSurveyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_id' => \App\Models\Client::decodeId($this->input('client_id')) ?? $this->input('client_id'),
        ]);
    }

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
