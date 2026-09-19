<?php

namespace App\Http\Requests;

use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveStageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::in(Opportunity::STAGES)],
            'lost_reason' => ['required_if:stage,lost', 'nullable', 'string', 'max:500'],
            'probability' => ['sometimes', 'integer', 'min:0', 'max:100'],
            // reopen_note required only when reopening won/lost — enforced in OpportunityService (needs current stage).
            'reopen_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'lost_reason.required_if' => 'Tell us why this was lost — it powers win/loss analytics.',
        ];
    }
}
