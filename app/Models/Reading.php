<?php

namespace App\Models;

use App\Enums\WordOutcome;
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
 * @property float|null $accuracy Once finished: the share of the Story's words read correctly on the first try, 0 to 1.
 * @property int|null $stars Once finished: 1 to 3, earned from the Accuracy.
 * @property float|null $reading_speed Once finished: words correct on the first try per minute of reading time.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Child $child
 * @property-read Story $story
 * @property-read Collection<int, WordResult> $wordResults
 */
#[Fillable(['started_at', 'finished_at', 'next_sentence_position', 'reading_time_ms', 'accuracy', 'stars', 'reading_speed'])]
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
     * Whether some misread words still wait for their retry or Syllable help.
     * A stub until ticket 06 implements retries.
     */
    public function hasPendingRetries(): bool
    {
        return false;
    }

    /**
     * Finish the Reading once every sentence is read and no retry is pending:
     * store the finish time, Accuracy, Stars and Reading speed. Does nothing
     * if the Reading is already finished or not complete yet.
     */
    public function finishIfComplete(): void
    {
        if ($this->finished_at !== null
            || $this->next_sentence_position < $this->story->sentences()->count()
            || $this->hasPendingRetries()) {
            return;
        }

        $wordCount = $this->story->words()->count();
        $correctFirstTry = $this->wordResults()->where('outcome', WordOutcome::CorrectFirstTry)->count();

        $this->forceFill([
            'finished_at' => now(),
            'accuracy' => $wordCount > 0 ? $correctFirstTry / $wordCount : 0.0,
            'stars' => match (true) {
                $correctFirstTry * 100 >= $wordCount * 95 => 3,
                $correctFirstTry * 100 >= $wordCount * 85 => 2,
                default => 1,
            },
            'reading_speed' => $this->reading_time_ms > 0 ? $correctFirstTry / ($this->reading_time_ms / 60000) : 0.0,
        ])->save();
    }

    /**
     * Get the Story's words the Child did not read correctly on the first try,
     * in Story order, each once, without the punctuation around them.
     *
     * @return list<string>
     */
    public function wordsToPractise(): array
    {
        $correctWordIds = $this->wordResults()->where('outcome', WordOutcome::CorrectFirstTry)->pluck('word_id');

        return array_values($this->story->words()
            ->whereNotIn('words.id', $correctWordIds)
            ->reorder()
            ->orderBy('sentences.position')
            ->orderBy('words.position')
            ->get(['words.text', 'words.normalized'])
            ->unique('normalized')
            ->map(fn (Word $word): string => (string) preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $word->text))
            ->all());
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
            'accuracy' => 'float',
            'stars' => 'integer',
            'reading_speed' => 'float',
        ];
    }
}
