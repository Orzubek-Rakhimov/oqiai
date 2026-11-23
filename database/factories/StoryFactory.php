<?php

namespace Database\Factories;

use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Stories without sentences. Story text comes from Story files, so tests
 * that need sentences and words load fixture files with stories:load.
 *
 * @extends Factory<Story>
 */
class StoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->randomElement([
            'Ali va mushuk', 'Kichik qush', 'Bog\'dagi olma', 'Dengiz bo\'yida', 'Qishloqda bir kun',
            'Do\'stlar', 'Yomg\'ir', 'Kutubxonada', 'Oq ayiq', 'Mening oilam',
        ]);

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'level' => fake()->numberBetween(1, 3),
            'minutes' => fake()->numberBetween(1, 5),
        ];
    }
}
