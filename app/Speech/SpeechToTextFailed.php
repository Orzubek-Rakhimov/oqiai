<?php

namespace App\Speech;

use RuntimeException;

/**
 * The speech-to-text service failed or timed out. The attempt is not scored.
 */
class SpeechToTextFailed extends RuntimeException {}
