<?php

namespace App\Models;

use App\Enums\WordOutcome;
use Database\Factories\WordResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How one word of a Story went in one Reading. There is at most one per word per Reading.
 *
 * @property int $id
 * @property int $reading_id
 * @property int $word_id
 * @property WordOutcome $outcome
 * @property Carbon $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Reading $reading
 * @property-read Word $word
 */
#[Fillable(['word_id', 'outcome', 'recorded_at'])]
class WordResult extends Model
{
    /** @use HasFactory<WordResultFactory> */
    use HasFactory;

    /**
     * Get the Reading the result belongs to.
     *
     * @return BelongsTo<Reading, $this>
     */
    public function reading(): BelongsTo
    {
        return $this->belongsTo(Reading::class);
    }

    /**
     * Get the word the result is for.
     *
     * @return BelongsTo<Word, $this>
     */
    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => WordOutcome::class,
            'recorded_at' => 'datetime',
        ];
    }
}
