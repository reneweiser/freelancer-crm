<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectItem>
 */
class ProjectItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'description' => fake()->sentence(),
            'quantity' => fake()->randomFloat(2, 1, 10),
            'unit' => fake()->randomElement(['Stück', 'Stunden', 'Tage', 'Pauschal']),
            'unit_price' => fake()->randomFloat(2, 50, 500),
            'position' => 1,
        ];
    }
}
