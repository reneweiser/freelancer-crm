<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecurringTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'frequency' => ['sometimes', Rule::enum(TaskFrequency::class)],
            'next_due_at' => ['sometimes', 'date'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'started_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'billing_notes' => ['nullable', 'string', 'max:500'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'frequency.enum' => 'Invalid frequency. Use "weekly", "monthly", "quarterly", or "yearly".',
            'next_due_at.date' => 'Next due date must be a valid date.',
            'ends_at.after_or_equal' => 'End date must be on or after start date.',
            'amount.min' => 'Amount must be 0 or greater.',
        ];
    }
}
