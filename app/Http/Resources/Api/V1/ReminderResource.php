<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Reminder
 */
class ReminderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'due_at' => $this->due_at?->toIso8601String(),
            'snoozed_until' => $this->snoozed_until?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'notified_at' => $this->notified_at?->toIso8601String(),
            'recurrence' => $this->recurrence?->value,
            'recurrence_label' => $this->recurrence?->getLabel(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->getLabel(),
            'priority_color' => $this->priority->getColor(),
            'is_system' => $this->is_system,
            'system_type' => $this->system_type,
            'is_overdue' => $this->is_overdue,
            'is_due_today' => $this->is_due_today,
            'effective_due_at' => $this->effective_due_at?->toIso8601String(),
            'remindable_type' => $this->remindable_type ? class_basename($this->remindable_type) : null,
            'remindable_id' => $this->remindable_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'remindable' => $this->whenLoaded('remindable', function () {
                return match ($this->remindable_type) {
                    \App\Models\Client::class => new ClientResource($this->remindable),
                    \App\Models\Project::class => new ProjectResource($this->remindable),
                    \App\Models\Invoice::class => new InvoiceResource($this->remindable),
                    default => null,
                };
            }),
        ];
    }
}
