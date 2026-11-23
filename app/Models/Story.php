<?php

namespace App\Models;

use Database\Factories\StoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * A Story: a short Uzbek text a Child reads aloud, loaded from a Story file.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property int $level
 * @property int $minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Sentence> $sentences
 * @property-read Collection<int, Word> $words
 * @property-read Collection<int, Reading> $readings
 */
#[Fillable(['slug', 'title', 'level', 'minutes'])]
class Story extends Model
{
    /** @use HasFactory<StoryFactory> */
    use HasFactory;

    /**
     * Get the Story's sentences in reading order.
     *
     * @return HasMany<Sentence, $this>
     */
    public function sentences(): HasMany
    {
        return $this->hasMany(Sentence::class)->orderBy('position');
    }

    /**
     * Get every word of the Story.
     *
     * @return HasManyThrough<Word, Sentence, $this>
     */
    public function words(): HasManyThrough
    {
        return $this->hasManyThrough(Word::class, Sentence::class);
    }

    /**
     * Get the Readings of this Story.
     *
     * @return HasMany<Reading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    /**
     * Get the Story after this one in library order (easiest Level first, then by title), or null for the last one.
     */
    public function next(): ?self
    {
        return self::query()
            ->where(fn ($query) => $query
                ->where('level', '>', $this->level)
                ->orWhere(fn ($query) => $query->where('level', $this->level)->where('title', '>', $this->title)))
            ->orderBy('level')
            ->orderBy('title')
            ->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'minutes' => 'integer',
        ];
    }
}
