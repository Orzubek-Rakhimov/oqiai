<?php

namespace App\Http\Presenters;

use App\Models\Reading;
use App\Models\Sentence;
use App\Models\Word;
use App\Speech\WordClips;

/**
 * What the reading screen shows of a Reading: the sentence to read, the
 * misread word to deal with first and how far the Child has got. Used for
 * the page's props and for the replies to each sentence, retry and
 * Syllable help.
 */
final class ReadingScreen
{
    /**
     * Get the state the reading screen moves on to after a sentence, a retry
     * or Syllable help.
     *
     * @return array{pendingWord: array{stage: 'retry'|'syllable_help', sentence: int, position: int, text: string, syllables: list<string>, clip: string|null}|null, progress: array{read: int, total: int}, nextSentence: array{position: int, words: list<string>}|null}
     */
    public static function afterStep(Reading $reading): array
    {
        return [
            'pendingWord' => self::pendingWord($reading),
            'progress' => $reading->progress(),
            'nextSentence' => self::sentence($reading->nextSentence()),
        ];
    }

    /**
     * Get a sentence as the reading screen shows it: its position and its
     * words as displayed, or null once every sentence is read.
     *
     * @return array{position: int, words: list<string>}|null
     */
    public static function sentence(?Sentence $sentence): ?array
    {
        if ($sentence === null) {
            return null;
        }

        return [
            'position' => $sentence->position,
            'words' => array_values($sentence->words->map(fn (Word $word): string => $word->text)->all()),
        ];
    }

    /**
     * Get the Reading's pending word as the reading screen shows it, with the
     * URL of its voice clip, or null when there is none.
     *
     * @return array{stage: 'retry'|'syllable_help', sentence: int, position: int, text: string, syllables: list<string>, clip: string|null}|null
     */
    public static function pendingWord(Reading $reading): ?array
    {
        $word = $reading->pendingWord();

        if ($word === null) {
            return null;
        }

        return [
            'stage' => $reading->syllable_help_word_id === null ? 'retry' : 'syllable_help',
            'sentence' => $word->sentence->position,
            'position' => $word->position,
            'text' => $word->text,
            'syllables' => $word->syllables,
            'clip' => WordClips::url($word),
        ];
    }
}
