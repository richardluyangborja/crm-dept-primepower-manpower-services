<?php

namespace App\Http\Requests;

use App\Models\SurveyTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSurveyTemplateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['nps', 'csat', 'custom'])],
            'questions' => ['required', 'array', 'min:1', 'max:10'],
            'questions.*.q' => ['required', 'string', 'max:500'],
            'questions.*.scale' => ['sometimes', 'integer', 'min:2', 'max:10'],
            'questions.*.options' => ['sometimes', 'array', 'min:2', 'max:6'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
