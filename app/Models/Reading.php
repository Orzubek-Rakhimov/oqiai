<?php

namespace App\Models;

use Database\Factories\ReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A Reading: one attempt by a Child to read one Story from start to finish.
 * An empty finished_at means the Reading is unfinished.
 *
 * @property int $id
 * @property int $child_id
 * @property int $story_id
 * @property int $next_sentence_position The position of the next sentence to read; equal to the sentence count once all are read.
 * @property int $reading_time_ms The accumulated length of the Child's recordings, without Syllable help.
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Child $child
 * @property-read Story $story
 * @property-read Collection<int, WordResult> $wordResults
 */
#[Fillable(['started_at', 'finished_at', 'next_sentence_position', 'reading_time_ms'])]
class Reading extends Model
{
    /** @use HasFactory<ReadingFactory> */
    use HasFactory;

    /**
     * Get the Child who is reading.
     *
     * @return BelongsTo<Child, $this>
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /**
     * Get the Story being read.
     *
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * Get the results of the words read so far.
     *
     * @return HasMany<WordResult, $this>
     */
    public function wordResults(): HasMany
    {
        return $this->hasMany(WordResult::class);
    }

    /**
     * Get the sentence the Child reads next, or null once every sentence is read.
     */
    public function nextSentence(): ?Sentence
    {
        return $this->story->sentences()->where('position', $this->next_sentence_position)->first();
    }

    /**
     * Get how many of the Story's sentences have been read, out of all of them.
     *
     * @return array{read: int, total: int}
     */
    public function progress(): array
    {
        return [
            'read' => $this->next_sentence_position,
            'total' => $this->story->sentences()->count(),
        ];
    }

    /**
     * Scope the query to unfinished Readings.
     *
     * @param  Builder<Reading>  $query
     */
    #[Scope]
    protected function unfinished(Builder $query): void
    {
        $query->whereNull('finished_at');
    }

    /**
     * Scope the query to finished Readings.
     *
     * @param  Builder<Reading>  $query
     */
    #[Scope]
    protected function finished(Builder $query): void
    {
        $query->whereNotNull('finished_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'next_sentence_position' => 'integer',
            'reading_time_ms' => 'integer',
        ];
    }
}
