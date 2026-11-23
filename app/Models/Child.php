<?php

namespace App\Models;

use App\Enums\Avatar;
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
