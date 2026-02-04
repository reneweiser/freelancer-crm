<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Project
 */
class ProjectResource extends JsonResource
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
            'reference' => $this->reference,
            'type' => $this->type->value,
            'type_label' => $this->type->getLabel(),
            'hourly_rate' => $this->hourly_rate ? (float) $this->hourly_rate : null,
            'fixed_price' => $this->fixed_price ? (float) $this->fixed_price : null,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'status_color' => $this->status->getColor(),
            'allowed_transitions' => collect($this->status->allowedTransitions())
                ->map(fn ($status) => $status->value)
                ->values()
                ->toArray(),
            'offer_date' => $this->offer_date?->toDateString(),
            'offer_valid_until' => $this->offer_valid_until?->toDateString(),
            'offer_sent_at' => $this->offer_sent_at?->toIso8601String(),
            'offer_accepted_at' => $this->offer_accepted_at?->toIso8601String(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'notes' => $this->notes,
            'total_value' => $this->total_value,
            'total_hours' => $this->total_hours,
            'billable_hours' => $this->billable_hours,
            'unbilled_hours' => $this->unbilled_hours,
            'unbilled_amount' => $this->unbilled_amount,
            'can_be_invoiced' => $this->canBeInvoiced(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'client' => new ClientResource($this->whenLoaded('client')),
            'items' => ProjectItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
