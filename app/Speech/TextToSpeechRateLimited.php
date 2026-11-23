<?php

namespace App\Speech;

use Throwable;

/**
 * The voice service refused for now: too many requests, or the quota is
 * used up. The clip is tried again later, after Retry-After when the
 * service gave one.
 */
class TextToSpeechRateLimited extends TextToSpeechFailed
{
    public function __construct(string $message, public readonly ?int $retryAfterSeconds = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
