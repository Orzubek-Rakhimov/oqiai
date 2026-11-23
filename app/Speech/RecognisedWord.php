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
}
