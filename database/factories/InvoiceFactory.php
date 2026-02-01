<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'client_id' => Client::factory(),
            'number' => fn () => Invoice::generateNextNumber(),
            'status' => InvoiceStatus::Draft,
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
            'vat_rate' => 19.00,
            'subtotal' => 0,
            'vat_amount' => 0,
            'total' => 0,
            'service_period_start' => now()->subMonth(),
            'service_period_end' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => InvoiceStatus::Draft]);
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => InvoiceStatus::Sent]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Overdue,
            'due_at' => now()->subDays(7),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
            'payment_method' => 'Überweisung',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => InvoiceStatus::Cancelled]);
    }

    public function withTotals(float $subtotal = 1000.00): static
    {
        $vatRate = 19.00;
        $vatAmount = $subtotal * ($vatRate / 100);

        return $this->state(fn () => [
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total' => $subtotal + $vatAmount,
        ]);
    }
}
