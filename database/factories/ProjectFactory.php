<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'client_id' => Client::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'type' => ProjectType::Fixed,
            'fixed_price' => fake()->randomFloat(2, 500, 10000),
            'status' => ProjectStatus::Draft,
            'offer_date' => now(),
            'offer_valid_until' => now()->addDays(30),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ProjectStatus::Draft]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Sent,
            'offer_sent_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Accepted,
            'offer_sent_at' => now()->subDays(5),
            'offer_accepted_at' => now(),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::InProgress,
            'offer_sent_at' => now()->subWeek(),
            'offer_accepted_at' => now()->subDays(5),
            'start_date' => now()->subDays(3),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Completed,
            'offer_sent_at' => now()->subMonth(),
            'offer_accepted_at' => now()->subWeeks(3),
            'start_date' => now()->subWeeks(2),
            'end_date' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => ProjectStatus::Cancelled]);
    }

    public function declined(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Declined,
            'offer_sent_at' => now()->subDays(5),
        ]);
    }

    public function hourly(): static
    {
        return $this->state(fn () => [
            'type' => ProjectType::Hourly,
            'hourly_rate' => fake()->randomFloat(2, 80, 150),
            'fixed_price' => null,
        ]);
    }

    public function fixed(): static
    {
        return $this->state(fn () => [
            'type' => ProjectType::Fixed,
            'fixed_price' => fake()->randomFloat(2, 500, 10000),
            'hourly_rate' => null,
        ]);
    }
}
