<?php

namespace App\Speech;

use RuntimeException;

/**
 * The text-to-speech service failed or timed out. No clip is saved.
 */
class TextToSpeechFailed extends RuntimeException {}
