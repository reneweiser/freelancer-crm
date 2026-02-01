<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    public function definition(): array
    {
        $durationMinutes = fake()->numberBetween(15, 480);
        $startedAt = Carbon::instance(fake()->dateTimeBetween('-1 month', 'now'))
            ->setTime(fake()->numberBetween(8, 17), 0, 0);
        $endedAt = $startedAt->copy()->addMinutes($durationMinutes);

        return [
            'user_id' => User::factory(),
            'project_id' => Project::factory()->hourly(),
            'description' => fake()->sentence(),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'billable' => true,
        ];
    }

    public function billable(): static
    {
        return $this->state(fn () => ['billable' => true]);
    }

    public function nonBillable(): static
    {
        return $this->state(fn () => ['billable' => false]);
    }

    public function withDuration(int $minutes): static
    {
        return $this->state(function (array $attributes) use ($minutes) {
            $startedAt = Carbon::parse($attributes['started_at'] ?? now())->setSecond(0);
            $endedAt = $startedAt->copy()->addMinutes($minutes);

            return [
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
            ];
        });
    }

    public function manualDuration(int $minutes): static
    {
        return $this->state(fn () => [
            'ended_at' => null,
            'duration_minutes' => $minutes,
        ]);
    }

    public function today(): static
    {
        $startedAt = now()->setTime(fake()->numberBetween(8, 17), 0, 0);

        return $this->state(fn () => [
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addMinutes(fake()->numberBetween(30, 240)),
        ]);
    }

    public function invoiced(): static
    {
        return $this->state(fn () => [
            'invoice_id' => \App\Models\Invoice::factory(),
        ]);
    }
}
