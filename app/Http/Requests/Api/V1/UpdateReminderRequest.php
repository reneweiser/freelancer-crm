<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ReminderPriority;
use App\Enums\ReminderRecurrence;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReminderRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['sometimes', 'required', 'date'],
            'priority' => ['nullable', Rule::enum(ReminderPriority::class)],
            'recurrence' => ['nullable', Rule::enum(ReminderRecurrence::class)],
            'remindable_type' => ['nullable', Rule::in(['Client', 'Project', 'Invoice'])],
            'remindable_id' => ['nullable', 'integer', 'required_with:remindable_type'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Reminder title is required.',
            'due_at.required' => 'Due date is required.',
            'due_at.date' => 'Due date must be a valid date.',
            'priority.enum' => 'Invalid priority. Use "low", "normal", or "high".',
            'recurrence.enum' => 'Invalid recurrence. Use "daily", "weekly", "monthly", "quarterly", or "yearly".',
            'remindable_type.in' => 'Invalid remindable type. Use "Client", "Project", or "Invoice".',
            'remindable_id.required_with' => 'Remindable ID is required when type is specified.',
        ];
    }

    /**
     * Map short remindable_type to full class name.
     */
    public function getRemindableClass(): ?string
    {
        return match ($this->remindable_type) {
            'Client' => Client::class,
            'Project' => Project::class,
            'Invoice' => Invoice::class,
            default => null,
        };
    }
}
