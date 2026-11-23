<?php

namespace App\Speech;

/**
 * The speech-to-text seam: what did the Child say in a short clip?
 *
 * A driver must report what was actually said, in order, and never
 * correct it toward the expected text. The audio is only passed through:
 * a driver must not keep it (ADR-0002).
 */
interface SpeechToText
{
    /**
     * Recognise the words spoken in the clip.
     *
     * @param  string  $audio  The raw bytes of the clip.
     * @param  string  $mimeType  The clip's format, such as "audio/webm".
     * @param  string  $expectedText  The text the Child was asked to read; a driver may use it as context or ignore it.
     * @param  string  $language  The language code, such as "uz".
     * @return list<RecognisedWord> The words in the order they were said; empty when nothing was heard.
     *
     * @throws SpeechToTextFailed When the service fails or times out.
     */
    public function transcribe(string $audio, string $mimeType, string $expectedText, string $language): array;
}
