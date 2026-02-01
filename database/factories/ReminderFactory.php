<?php

namespace Database\Factories;

use App\Enums\ReminderPriority;
use App\Enums\ReminderRecurrence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reminder>
 */
class ReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'due_at' => fake()->dateTimeBetween('now', '+7 days'),
            'priority' => ReminderPriority::Normal,
            'is_system' => false,
        ];
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => ReminderPriority::High,
        ]);
    }

    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => ReminderPriority::Low,
        ]);
    }

    public function recurring(ReminderRecurrence $recurrence = ReminderRecurrence::Weekly): static
    {
        return $this->state(fn (array $attributes) => [
            'recurrence' => $recurrence,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_at' => fake()->dateTimeBetween('-7 days', '-1 day'),
        ]);
    }

    public function dueToday(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_at' => now(),
        ]);
    }

    public function snoozed(int $hours = 24): static
    {
        return $this->state(fn (array $attributes) => [
            'snoozed_until' => now()->addHours($hours),
        ]);
    }

    public function system(string $type = 'overdue_invoice'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
            'system_type' => $type,
        ]);
    }
}
