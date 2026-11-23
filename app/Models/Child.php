<?php

namespace App\Models;

use App\Enums\Avatar;
use App\Enums\WordOutcome;
use Carbon\CarbonImmutable;
use Database\Factories\ChildFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * A Child: a reading profile inside a Parent's account. It never logs in.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int $grade
 * @property Avatar $avatar
 * @property Carbon|null $microphone_checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $parent
 * @property-read Collection<int, Reading> $readings
 */
#[Fillable(['name', 'grade', 'avatar'])]
class Child extends Model
{
    /** @use HasFactory<ChildFactory> */
    use HasFactory;

    /**
     * The time zone whose calendar days the Streak counts.
     */
    public const STREAK_TIMEZONE = 'Asia/Tashkent';

    /**
     * Get the Parent who owns this Child.
     *
     * @return BelongsTo<User, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the Child's Readings.
     *
     * @return HasMany<Reading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    /**
     * Get the Child's best Stars for each Story they have finished, keyed by Story id.
     *
     * @return SupportCollection<int, int>
     */
    public function bestStarsPerStory(): SupportCollection
    {
        return $this->readings()
            ->finished()
            ->groupBy('story_id')
            ->selectRaw('story_id, max(stars) as best_stars')
            ->toBase()
            ->pluck('best_stars', 'story_id')
            ->map(fn (mixed $stars): int => (int) $stars);
    }

    /**
     * Get the Child's Stars total: the sum of their best Stars per Story.
     */
    public function starsTotal(): int
    {
        return $this->bestStarsPerStory()->sum();
    }

    /**
     * Get the Child's Streak: the number of consecutive Tashkent calendar days
     * with at least one finished Reading, counting back from today, or from
     * yesterday if nothing has been finished today yet.
     */
    public function streak(): int
    {
        $daysWithAFinishedReading = $this->readings()
            ->finished()
            ->toBase()
            ->pluck('finished_at')
            ->map(fn (mixed $finishedAt): string => CarbonImmutable::parse((string) $finishedAt, 'UTC')
                ->setTimezone(self::STREAK_TIMEZONE)
                ->toDateString())
            ->flip();

        $day = CarbonImmutable::now(self::STREAK_TIMEZONE)->startOfDay();

        if (! $daysWithAFinishedReading->has($day->toDateString())) {
            $day = $day->subDay();
        }

        $streak = 0;

        while ($daysWithAFinishedReading->has($day->toDateString())) {
            $streak++;
            $day = $day->subDay();
        }

        return $streak;
    }

    /**
     * Get the number of distinct Tashkent days this week (Monday to Sunday)
     * on which the Child finished at least one Reading.
     */
    public function daysPractisedThisWeek(): int
    {
        return $this->readingsFinishedSince(self::startOfTashkentWeek())
            ->map(fn (array $reading): string => $reading['finished_at']->toDateString())
            ->unique()
            ->count();
    }

    /**
     * Get the number of different Stories the Child finished this Tashkent week.
     */
    public function storiesFinishedThisWeek(): int
    {
        return $this->readingsFinishedSince(self::startOfTashkentWeek())
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
        $speedsByWeek = $this->readingsFinishedSince(self::startOfTashkentWeek($weeksBefore))
            ->groupBy(fn (array $reading): string => $reading['finished_at']->startOfWeek(CarbonImmutable::MONDAY)->toDateString())
            ->map(fn (SupportCollection $readings): ?float => $readings->pluck('reading_speed')->filter(fn (?float $speed): bool => $speed !== null)->avg());

        return array_values(collect(range($weeksBefore, 0))
            ->map(function (int $weeksAgo) use ($speedsByWeek): array {
                $weekStart = self::startOfTashkentWeek($weeksAgo)->toDateString();
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
     * ranked by how often, then alphabetically.
     *
     * @return list<array{word: string, count: int}>
     */
    public function hardWords(int $limit = 5): array
    {
        return array_values(WordResult::query()
            ->join('readings', 'readings.id', '=', 'word_results.reading_id')
            ->join('words', 'words.id', '=', 'word_results.word_id')
            ->where('readings.child_id', $this->id)
            ->whereIn('word_results.outcome', [WordOutcome::CorrectOnRetry, WordOutcome::NeededSyllableHelp])
            ->where('word_results.recorded_at', '>=', now()->subDays(7))
            ->groupBy('words.normalized')
            ->selectRaw('words.normalized as word, count(*) as times')
            ->orderByDesc('times')
            ->orderBy('word')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn (object $hardWord): array => ['word' => (string) $hardWord->word, 'count' => (int) $hardWord->times])
            ->all());
    }

    /**
     * Get the Monday that starts the current Tashkent week, or a week before it.
     */
    private static function startOfTashkentWeek(int $weeksAgo = 0): CarbonImmutable
    {
        return CarbonImmutable::now(self::STREAK_TIMEZONE)
            ->startOfWeek(CarbonImmutable::MONDAY)
            ->subWeeks($weeksAgo);
    }

    /**
     * Get the Child's Readings finished since the given moment, with their
     * finish time in Tashkent.
     *
     * @return SupportCollection<int, array{story_id: int, reading_speed: float|null, finished_at: CarbonImmutable}>
     */
    private function readingsFinishedSince(CarbonImmutable $since): SupportCollection
    {
        return $this->readings()
            ->finished()
            ->where('finished_at', '>=', $since->utc())
            ->toBase()
            ->get(['story_id', 'reading_speed', 'finished_at'])
            ->map(fn (object $reading): array => [
                'story_id' => (int) $reading->story_id,
                'reading_speed' => $reading->reading_speed === null ? null : (float) $reading->reading_speed,
                'finished_at' => CarbonImmutable::parse((string) $reading->finished_at, 'UTC')->setTimezone(self::STREAK_TIMEZONE),
            ]);
    }

    /**
     * Get the Level suggested first for the Child's Grade: Grade 1 gets
     * Level 1, Grade 2 gets Level 2 and Grades 3 and 4 get Level 3.
     */
    public function suggestedLevel(): int
    {
        return min($this->grade, 3);
    }

    /**
     * Get the Story to suggest next: the first Story, in library order, at the
     * suggested Level that the Child has not finished, falling back to any
     * Story they have not finished. Null once every Story is finished.
     */
    public function suggestedStory(): ?Story
    {
        $unfinishedStories = Story::query()
            ->whereNotIn('id', $this->readings()->finished()->select('story_id'))
            ->orderBy('level')
            ->orderBy('title');

        return (clone $unfinishedStories)->where('level', $this->suggestedLevel())->first()
            ?? $unfinishedStories->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'avatar' => Avatar::class,
            'microphone_checked_at' => 'datetime',
        ];
    }
}
