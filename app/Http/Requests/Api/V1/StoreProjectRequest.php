<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ProjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:50'],
            'type' => ['required', Rule::enum(ProjectType::class)],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'required_if:type,hourly'],
            'fixed_price' => ['nullable', 'numeric', 'min:0', 'required_if:type,fixed'],
            'offer_date' => ['nullable', 'date'],
            'offer_valid_until' => ['nullable', 'date', 'after_or_equal:offer_date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
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
            'title.required' => 'Project title is required.',
            'type.required' => 'Project type is required (fixed or hourly).',
            'type.enum' => 'Invalid project type. Use "fixed" or "hourly".',
            'hourly_rate.required_if' => 'Hourly rate is required for hourly projects.',
            'fixed_price.required_if' => 'Fixed price is required for fixed-price projects.',
            'offer_valid_until.after_or_equal' => 'Offer valid until must be after or equal to offer date.',
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
        ];
    }
}
