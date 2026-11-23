<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\Reading;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reading>
 */
class ReadingFactory extends Factory
{
    /**
     * Define the model's default state: an unfinished Reading.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'child_id' => Child::factory(),
            'story_id' => Story::factory(),
            'started_at' => now()->subMinutes(10),
            'finished_at' => null,
        ];
    }

    /**
     * A finished Reading.
     */
    public function finished(): static
    {
        return $this->state(fn (array $attributes): array => [
            'finished_at' => now(),
        ]);
    }
}
