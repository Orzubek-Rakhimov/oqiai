<?php

namespace App\Speech;

use App\Enums\SpeechPause;

/**
 * Voices the Syllable help clips with Aisha (VoiceLab), a voice built for
 * Uzbek, on its sync endpoint (POST /api/v1/tts/post/) with the built-in
 * Gulnoza model and one of its moods.
 *
 * Aisha has no pause markup, so the parts are joined with punctuation: a
 * full stop for a long pause ("ku. tub. kutub.") and a comma for a short
 * one. That keeps a word's clip to one voicing. The pace is Aisha's speed
 * setting. Aisha answers with the path of the WAV it made, which is then
 * downloaded: two requests per clip. A 429 or a used-up balance (402) is a
 * rate limit, so the clip waits before it is tried again.
 */
final readonly class AishaTextToSpeech implements TextToSpeech
{
    /**
     * The most characters Aisha voices in one request with an API key.
     */
    public const int MAX_CHARACTERS = 1000;

    private const float NORMAL_SPEED = 1.0;

    private AishaApi $api;

    /**
     * @param  string  $mood  One of Gulnoza's moods: Neutral, Cheerful, Happy or Sad.
     * @param  float  $slowSpeed  The speed for slow reading, from 0.5 to 2.0 (1.0 is the usual pace).
     */
    public function __construct(string $apiKey, private string $model, private string $mood, private float $slowSpeed, int $timeoutSeconds)
    {
        $this->api = new AishaApi($apiKey, $timeoutSeconds, TextToSpeechFailed::class, TextToSpeechRateLimited::class);
    }

    public function speak(array $parts, SpeakingOptions $options): string
    {
        $transcript = implode($options->pauseBetweenParts === SpeechPause::Long ? '. ' : ', ', $parts).'.';

        if (mb_strlen($transcript) > self::MAX_CHARACTERS) {
            throw new TextToSpeechFailed('Aisha voices at most '.self::MAX_CHARACTERS.' characters in one request.');
        }

        $answer = $this->api->post('/api/v1/tts/post/', [
            'transcript' => $transcript,
            'language' => $options->language,
            'model' => $this->model,
            'mood' => $this->mood,
            'speed' => (string) ($options->slowly ? $this->slowSpeed : self::NORMAL_SPEED),
        ]);

        if (! is_string($answer['audio_path'] ?? null) || $answer['audio_path'] === '') {
            throw new TextToSpeechFailed('The Aisha API gave no audio path.');
        }

        $wav = $this->api->download($answer['audio_path']);

        if (! str_starts_with($wav, 'RIFF') || substr($wav, 8, 4) !== 'WAVE') {
            throw new TextToSpeechFailed('The Aisha API gave no WAV audio.');
        }

        return $wav;
    }
}
