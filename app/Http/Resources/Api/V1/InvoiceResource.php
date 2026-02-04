<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'project_id' => $this->project_id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'status_color' => $this->status->getColor(),
            'allowed_transitions' => collect($this->status->allowedTransitions())
                ->map(fn ($status) => $status->value)
                ->values()
                ->toArray(),
            'issued_at' => $this->issued_at?->toDateString(),
            'due_at' => $this->due_at?->toDateString(),
            'paid_at' => $this->paid_at?->toDateString(),
            'payment_method' => $this->payment_method,
            'subtotal' => (float) $this->subtotal,
            'vat_rate' => (float) $this->vat_rate,
            'vat_amount' => (float) $this->vat_amount,
            'total' => (float) $this->total,
            'formatted_total' => $this->formatted_total,
            'service_period_start' => $this->service_period_start?->toDateString(),
            'service_period_end' => $this->service_period_end?->toDateString(),
            'notes' => $this->notes,
            'footer_text' => $this->footer_text,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'client' => new ClientResource($this->whenLoaded('client')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
