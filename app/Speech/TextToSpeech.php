<?php

namespace App\Speech;

/**
 * The text-to-speech seam: the voice that reads a word, or its syllables,
 * to the Child in Syllable help.
 *
 * The text comes as parts read in order with a pause between each, so a
 * driver can mark the pauses its own way (inline pause tags, SSML breaks).
 */
interface TextToSpeech
{
    /**
     * Read the parts aloud, in order, with a pause between each.
     *
     * @param  non-empty-list<string>  $parts  The text to read, such as the syllables ["uy", "ga"] or the word ["uyga"].
     * @param  SpeakingOptions  $options  How to read it: the pace, the pause between parts and the language.
     * @return string The bytes of a WAV file.
     *
     * @throws TextToSpeechFailed When the service fails or times out.
     */
    public function speak(array $parts, SpeakingOptions $options): string;
}
