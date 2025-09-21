<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company . ' ' . $this->faker->randomElement(['Hospital', 'Department', 'Clinic', 'Center']),
            'description' => $this->faker->optional()->sentence(),
            'type' => $this->faker->randomElement(['hospital', 'clinician_group']),
            'parent_id' => null,
            'level' => 0,
            'path' => null,
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
        ];
    }

    /**
     * Indicate that the group is a hospital.
     */
    public function hospital(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'hospital',
        ]);
    }

    /**
     * Indicate that the group is a clinician group.
     */
    public function clinicianGroup(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'clinician_group',
        ]);
    }

    /**
     * Indicate that the group is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the group is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
