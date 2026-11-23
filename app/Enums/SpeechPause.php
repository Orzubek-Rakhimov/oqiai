<?php

namespace App\Enums;

/**
 * How long the voice pauses between the parts of what it reads.
 */
enum SpeechPause: string
{
    case Short = 'short';
    case Long = 'long';
}
