<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ClientType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
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
            'type' => ['required', Rule::enum(ClientType::class)],
            'company_name' => ['nullable', 'string', 'max:255'],
            'vat_id' => ['nullable', 'string', 'max:50'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'street' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:2'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Client type is required (company or individual).',
            'type.enum' => 'Invalid client type. Use "company" or "individual".',
            'contact_name.required' => 'Contact name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
        ];
    }
}
