<?php

namespace App\Speech;

/**
 * One word as the speech-to-text service heard it, with optional timing
 * (seconds from the start of the clip) and confidence (0 to 1).
 */
final readonly class RecognisedWord
{
    public function __construct(
        public string $text,
        public ?float $startSeconds = null,
        public ?float $endSeconds = null,
        public ?float $confidence = null,
    ) {}

    /**
     * Split a transcript into its words, in order, for a service that gives no word list.
     *
     * @return list<RecognisedWord>
     */
    public static function inTranscript(string $transcript): array
    {
        $words = preg_split('/\s+/u', trim($transcript), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(fn (string $word): self => new self($word), $words);
    }
}
