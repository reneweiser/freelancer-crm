<?php

namespace Database\Factories;

use App\Models\RecurringTask;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecurringTaskLog>
 */
class RecurringTaskLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recurring_task_id' => RecurringTask::factory(),
            'due_date' => fake()->date(),
            'action' => 'reminder_created',
        ];
    }

    public function reminderCreated(?Reminder $reminder = null): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'reminder_created',
            'reminder_id' => $reminder?->id,
        ]);
    }

    public function manuallyCompleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'manually_completed',
        ]);
    }

    public function skipped(?string $reason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'skipped',
            'notes' => $reason ?? fake()->optional()->sentence(),
        ]);
    }
}
