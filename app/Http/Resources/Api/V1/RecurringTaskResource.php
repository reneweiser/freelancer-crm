<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\RecurringTask
 */
class RecurringTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'title' => $this->title,
            'description' => $this->description,
            'frequency' => $this->frequency->value,
            'frequency_label' => $this->frequency->getLabel(),
            'frequency_color' => $this->frequency->getColor(),
            'next_due_at' => $this->next_due_at?->toDateString(),
            'last_run_at' => $this->last_run_at?->toDateString(),
            'started_at' => $this->started_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'amount' => $this->amount ? (float) $this->amount : null,
            'formatted_amount' => $this->formatted_amount,
            'billing_notes' => $this->billing_notes,
            'active' => $this->active,
            'is_overdue' => $this->is_overdue,
            'is_due_soon' => $this->is_due_soon,
            'has_ended' => $this->has_ended,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'client' => new ClientResource($this->whenLoaded('client')),
            'logs' => RecurringTaskLogResource::collection($this->whenLoaded('logs')),
        ];
    }
}
