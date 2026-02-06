<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeEntryRequest extends FormRequest
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
            'project_id' => ['sometimes', 'integer', 'exists:projects,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'started_at' => ['sometimes', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'billable' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.exists' => 'The specified project does not exist.',
            'started_at.date' => 'Start time must be a valid date.',
            'ended_at.after' => 'End time must be after start time.',
            'duration_minutes.min' => 'Duration must be at least 1 minute.',
        ];
    }
}
