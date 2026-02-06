<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TimeEntry
 */
class TimeEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'invoice_id' => $this->invoice_id,
            'description' => $this->description,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'duration_hours' => $this->duration_hours,
            'formatted_duration' => $this->formatted_duration,
            'billable' => $this->billable,
            'is_invoiced' => $this->isInvoiced(),
            'is_running' => $this->started_at !== null && $this->ended_at === null && $this->duration_minutes === null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
        ];
    }
}
