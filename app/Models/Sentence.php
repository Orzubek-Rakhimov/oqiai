<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One sentence of a Story, read aloud in one go.
 *
 * @property int $id
 * @property int $story_id
 * @property int $position
 * @property string $text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Story $story
 * @property-read Collection<int, Word> $words
 */
#[Fillable(['position', 'text'])]
class Sentence extends Model
{
    /**
     * Get the Story this sentence belongs to.
     *
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * Get the sentence's words in reading order.
     *
     * @return HasMany<Word, $this>
     */
    public function words(): HasMany
    {
        return $this->hasMany(Word::class)->orderBy('position');
    }

    /**
     * Get the sentence as the reading screen shows it: its position and its words as displayed.
     *
     * @return array{position: int, words: list<string>}
     */
    public function toReaderArray(): array
    {
        return [
            'position' => $this->position,
            'words' => array_values($this->words->map(fn (Word $word): string => $word->text)->all()),
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }
}
