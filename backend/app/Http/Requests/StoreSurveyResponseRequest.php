<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSurveyResponseRequest extends FormRequest
{
    public function authorize(): bool { return true; } // public token route

    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'min:0', 'max:10'],
            'answers' => ['sometimes', 'array'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
