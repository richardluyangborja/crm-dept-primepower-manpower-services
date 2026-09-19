<?php

namespace App\Http\Requests;

use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'opportunity_id' => ['nullable', 'exists:opportunities,id'],
            'type' => ['required', Rule::in(Activity::TYPES)],
            'subject' => ['required_if:type,email,meeting', 'nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'outcome' => ['nullable', 'string', 'max:50'],
            'occurred_at' => ['sometimes', 'date', 'before_or_equal:now'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            // Next-step hook (specs/07 → 08): create the follow-up in one call.
            'create_followup' => ['sometimes', 'boolean'],
            'followup_title' => ['required_if:create_followup,true', 'nullable', 'string', 'max:255'],
            'followup_due_at' => ['required_if:create_followup,true', 'nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required_if' => 'Emails and meetings need a subject line.',
            'occurred_at.before_or_equal' => 'You can only log things that already happened.',
            'attachments.*.max' => 'Each file must be ≤ 10 MB.',
            'attachments.*.mimes' => 'Allowed: pdf, jpg, png, doc, docx.',
        ];
    }
}
