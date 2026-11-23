<?php

namespace App\Reports;

use App\Enums\WordOutcome;
use App\Models\Child;
use App\Models\Word;
use App\Models\WordResult;
use App\Stories\UzbekWord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The Parent screen's figures for one Child, counted on the Child's
 * (Tashkent) calendar: this week's practice, the Reading speed trend and
 * the Hard words.
 */
final readonly class ChildProgress
{
    public function __construct(private Child $child) {}

    /**
     * Get the number of distinct Tashkent days this week (Monday to Sunday)
     * on which the Child finished at least one Reading.
     */
    public function daysPractisedThisWeek(): int
    {
        return $this->child->finishedReadings(self::startOfWeek())
            ->map(fn (array $reading): string => $reading['finished_at']->toDateString())
            ->unique()
            ->count();
    }

    /**
     * Get the number of different Stories the Child finished this Tashkent week.
     */
    public function storiesFinishedThisWeek(): int
    {
        return $this->child->finishedReadings(self::startOfWeek())
            ->pluck('story_id')
            ->unique()
            ->count();
    }

    /**
     * Get the average Reading speed of the Child's finished Readings for this
     * Tashkent week and each of the given number of weeks before it, oldest
     * first. A week with no finished Reading has no Reading speed.
     *
     * @return list<array{weekStart: string, readingSpeed: int|null}>
     */
    public function readingSpeedByWeek(int $weeksBefore = 3): array
    {
        $speedsByWeek = $this->child->finishedReadings(self::startOfWeek($weeksBefore))
            ->groupBy(fn (array $reading): string => $reading['finished_at']->startOfWeek(CarbonImmutable::MONDAY)->toDateString())
            ->map(fn (Collection $readings): ?float => $readings->pluck('reading_speed')->filter(fn (?float $speed): bool => $speed !== null)->avg());

        return array_values(collect(range($weeksBefore, 0))
            ->map(function (int $weeksAgo) use ($speedsByWeek): array {
                $weekStart = self::startOfWeek($weeksAgo)->toDateString();
                $averageSpeed = $speedsByWeek->get($weekStart);

                return [
                    'weekStart' => $weekStart,
                    'readingSpeed' => $averageSpeed === null ? null : (int) round($averageSpeed),
                ];
            })
            ->all());
    }

    /**
     * Get the Child's Hard words: words with a "correct on retry" or "needed
     * Syllable help" result in the last 7 days, grouped by normalised word and
     * ranked by how often, then alphabetically. Each is shown as a Story writes
     * it (the earliest of its hard occurrences), without the punctuation around it.
     *
     * @return list<array{word: string, count: int}>
     */
    public function hardWords(int $limit = 5): array
    {
        $hardWords = WordResult::query()
            ->join('readings', 'readings.id', '=', 'word_results.reading_id')
            ->join('words', 'words.id', '=', 'word_results.word_id')
            ->where('readings.child_id', $this->child->id)
            ->whereIn('word_results.outcome', [WordOutcome::CorrectOnRetry, WordOutcome::NeededSyllableHelp])
            ->where('word_results.recorded_at', '>=', now()->subDays(7))
            ->groupBy('words.normalized')
            ->selectRaw('words.normalized, min(words.id) as first_word_id, count(*) as times')
            ->orderByDesc('times')
            ->orderBy('words.normalized')
            ->limit($limit)
            ->toBase()
            ->get();

        $writtenForms = Word::query()->whereKey($hardWords->pluck('first_word_id'))->pluck('text', 'id');

        return array_values($hardWords
            ->map(fn (object $hardWord): array => [
                'word' => UzbekWord::asWritten((string) $writtenForms->get((int) $hardWord->first_word_id)),
                'count' => (int) $hardWord->times,
            ])
            ->all());
    }

    /**
     * Get the Monday that starts the current Tashkent week, or a week before it.
     */
    private static function startOfWeek(int $weeksAgo = 0): CarbonImmutable
    {
        return CarbonImmutable::now(Child::TIMEZONE)
            ->startOfWeek(CarbonImmutable::MONDAY)
            ->subWeeks($weeksAgo);
    }
}
