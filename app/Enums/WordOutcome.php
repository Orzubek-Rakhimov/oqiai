<?php

namespace App\Enums;

/**
 * How a Child got a word right during a Reading. A word misread on the
 * first try gets its outcome once its retry (or Syllable help) is done.
 */
enum WordOutcome: string
{
    case CorrectFirstTry = 'correct_first_try';
    case CorrectOnRetry = 'correct_on_retry';
    case NeededSyllableHelp = 'needed_syllable_help';
}
