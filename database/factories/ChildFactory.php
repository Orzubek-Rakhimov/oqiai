<?php

namespace Database\Factories;

use App\Enums\Avatar;
use App\Models\Child;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Child>
 */
class ChildFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Ali', 'Madina', 'Jasur', 'Zarina', 'Bobur', 'Malika']),
            'grade' => fake()->numberBetween(1, 4),
            'avatar' => fake()->randomElement(Avatar::cases()),
        ];
    }
}
