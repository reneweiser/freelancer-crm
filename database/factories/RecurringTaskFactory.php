<?php

namespace Database\Factories;

use App\Enums\TaskFrequency;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecurringTask>
 */
class RecurringTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->randomElement([
                'Website-Wartung',
                'Hosting-Verlängerung',
                'SSL-Zertifikat erneuern',
                'Backup-Überprüfung',
                'Security-Updates',
                'Domain-Verlängerung',
                'Wartungsvertrag',
            ]),
            'description' => fake()->optional()->paragraph(),
            'frequency' => fake()->randomElement(TaskFrequency::cases()),
            'next_due_at' => fake()->dateTimeBetween('now', '+30 days'),
            'active' => true,
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
            'user_id' => $client->user_id,
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => TaskFrequency::Weekly,
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => TaskFrequency::Monthly,
        ]);
    }

    public function quarterly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => TaskFrequency::Quarterly,
        ]);
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => TaskFrequency::Yearly,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_due_at' => fake()->dateTimeBetween('-7 days', '-1 day'),
        ]);
    }

    public function dueSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_due_at' => fake()->dateTimeBetween('now', '+3 days'),
        ]);
    }

    public function withAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => fake()->randomFloat(2, 50, 500),
            'billing_notes' => fake()->optional()->sentence(),
        ]);
    }

    public function withEndDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => now()->subYear(),
            'ends_at' => now()->addYear(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => now()->subYears(2),
            'ends_at' => now()->subDay(),
        ]);
    }
}
