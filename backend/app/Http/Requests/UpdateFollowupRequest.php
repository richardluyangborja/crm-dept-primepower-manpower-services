<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFollowupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'due_at' => ['sometimes', 'date', 'after_or_equal:today'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'owner_id' => ['sometimes', \Illuminate\Validation\Rule::exists('users', 'id')->where(fn ($q) => $q->whereIn('role', ['sales_rep', 'manager'])->where('is_active', true))],
        ];
    }
}
