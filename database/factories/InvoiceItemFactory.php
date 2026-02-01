<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->sentence(),
            'quantity' => fake()->randomFloat(2, 1, 10),
            'unit' => fake()->randomElement(['Stück', 'Stunden', 'Tage', 'Pauschal']),
            'unit_price' => fake()->randomFloat(2, 50, 500),
            'vat_rate' => 19.00,
            'position' => 1,
        ];
    }
}
