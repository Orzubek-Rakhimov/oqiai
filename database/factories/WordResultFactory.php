<?php

namespace Database\Factories;

use App\Enums\WordOutcome;
use App\Models\Reading;
use App\Models\WordResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Words come from Story files, so give each result its word with
 * `->for($word)`, and a Reading of that word's Story.
 *
 * @extends Factory<WordResult>
 */
class WordResultFactory extends Factory
{
    /**
     * Define the model's default state: correct on the first try.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reading_id' => Reading::factory(),
            'outcome' => WordOutcome::CorrectFirstTry,
            'recorded_at' => now(),
        ];
    }

    /**
     * A word read correctly on its retry.
     */
    public function correctOnRetry(): static
    {
        return $this->state(fn (array $attributes): array => [
            'outcome' => WordOutcome::CorrectOnRetry,
        ]);
    }

    /**
     * A word that needed Syllable help.
     */
    public function neededSyllableHelp(): static
    {
        return $this->state(fn (array $attributes): array => [
            'outcome' => WordOutcome::NeededSyllableHelp,
        ]);
    }
}
