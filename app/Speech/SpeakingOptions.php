<?php

namespace App\Speech;

use App\Enums\SpeechPause;

/**
 * How the voice reads: slowly or at a normal pace, how long it pauses
 * between the parts of the text, and in which language.
 */
final readonly class SpeakingOptions
{
    public function __construct(
        public bool $slowly = false,
        public SpeechPause $pauseBetweenParts = SpeechPause::Short,
        public string $language = 'uz',
    ) {}
}
