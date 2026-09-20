<?php

namespace App\Http\Requests;

use App\Models\SurveyTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSurveyTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['nps', 'csat', 'custom'])],
            'questions' => ['sometimes', 'array', 'min:1', 'max:10'],
            'questions.*.q' => ['required', 'string', 'max:500'],
            'questions.*.scale' => ['sometimes', 'integer', 'min:2', 'max:10'],
            'questions.*.options' => ['sometimes', 'array', 'min:2', 'max:6'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
