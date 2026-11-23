<?php

namespace App\Models;

use App\Enums\Avatar;
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
        ];
    }
}
