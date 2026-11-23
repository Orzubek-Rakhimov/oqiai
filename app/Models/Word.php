<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One word of a Story sentence: the text as displayed, its normalised form
 * for matching what the Child said, and its syllables for Syllable help.
 *
 * @property int $id
 * @property int $sentence_id
 * @property int $position
 * @property string $text
 * @property string $normalized
 * @property list<string> $syllables
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sentence $sentence
 */
#[Fillable(['position', 'text', 'normalized', 'syllables'])]
class Word extends Model
{
    /**
     * Get the sentence this word belongs to.
     *
     * @return BelongsTo<Sentence, $this>
     */
    public function sentence(): BelongsTo
    {
        return $this->belongsTo(Sentence::class);
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
            'syllables' => 'array',
        ];
    }
}
