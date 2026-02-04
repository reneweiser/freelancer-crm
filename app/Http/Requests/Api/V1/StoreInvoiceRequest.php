<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
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
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'issued_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_period_start' => ['nullable', 'date'],
            'service_period_end' => ['nullable', 'date', 'after_or_equal:service_period_start'],
            'notes' => ['nullable', 'string'],
            'footer_text' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'Client ID is required.',
            'client_id.exists' => 'The specified client does not exist.',
            'project_id.exists' => 'The specified project does not exist.',
            'due_at.after_or_equal' => 'Due date must be after or equal to issue date.',
            'service_period_end.after_or_equal' => 'Service period end must be after or equal to start.',
            'items.required' => 'At least one line item is required.',
            'items.min' => 'At least one line item is required.',
            'items.*.description.required' => 'Each item requires a description.',
            'items.*.quantity.required' => 'Each item requires a quantity.',
            'items.*.unit_price.required' => 'Each item requires a unit price.',
        ];
    }
}
