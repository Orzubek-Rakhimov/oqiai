<?php

namespace App\Speech;

use App\Enums\SpeechPause;
use Illuminate\Http\Client\Response;

/**
 * Voices the Syllable help clips with Aisha (VoiceLab), a voice built for
 * Uzbek, through VoiceLab's developer API (POST /v1/tts) with a model
 * (such as lison) and one of its voices.
 *
 * VoiceLab has no pause markup, so the parts are joined with punctuation: a
 * full stop for a long pause ("ku. tub. kutub.") and a comma for a short
 * one. That keeps a word's clip to one voicing. The pace is VoiceLab's speed
 * setting. A clip made within the request comes back as the WAV itself; a
 * slower one comes back 202 and is polled, then downloaded from its signed
 * URL. The voice is a voice_… ID, or a name looked up in the catalog of
 * the language and model (GET /v1/voices), the first voice when none is
 * set. A 429 or used-up credits (402) is a rate limit, so the clip waits
 * before it is tried again.
 */
final readonly class AishaTextToSpeech implements TextToSpeech
{
    /**
     * The most characters VoiceLab voices in one request.
     */
    public const int MAX_CHARACTERS = 5000;

    private const float NORMAL_SPEED = 1.0;

    private AishaApi $api;

    private AishaApi $catalog;

    /**
     * @param  string  $model  A model ID from VoiceLab's catalog (GET /v1/tts/languages), such as lison.
     * @param  string  $voice  A voice ID (voice_…), a voice's name from the catalog, or empty for the first voice listed.
     * @param  float  $slowSpeed  The speed for slow reading, from 0.5 to 2.0 (1.0 is the usual pace).
     */
    public function __construct(string $apiKey, string $baseUrl, private string $model, private string $voice, private float $slowSpeed, int $timeoutSeconds)
    {
        $this->api = new AishaApi($apiKey, $baseUrl, $timeoutSeconds, 'Text to speech permission at level Access', TextToSpeechFailed::class, TextToSpeechRateLimited::class);
        $this->catalog = new AishaApi($apiKey, $baseUrl, $timeoutSeconds, 'Voices permission at level Read (or AISHA_TTS_VOICE set to a voice_… ID)', TextToSpeechFailed::class);
    }

    public function speak(array $parts, SpeakingOptions $options): string
    {
        $text = implode($options->pauseBetweenParts === SpeechPause::Long ? '. ' : ', ', $parts).'.';

        if (mb_strlen($text) > self::MAX_CHARACTERS) {
            throw new TextToSpeechFailed('VoiceLab voices at most '.self::MAX_CHARACTERS.' characters in one request.');
        }

        $response = $this->api->postJson('/v1/tts', [
            'text' => $text,
            'language' => $options->language,
            'voice_id' => $this->voiceId($options->language),
            'model_id' => $this->model,
            'speed' => $options->slowly ? $this->slowSpeed : self::NORMAL_SPEED,
        ], 'audio/wav, application/json');

        return $this->wav($response->status() === 202 ? $this->generated($response) : $response->body());
    }

    /**
     * Wait for a generation VoiceLab is still making, then download its WAV.
     */
    private function generated(Response $accepted): string
    {
        $job = $this->api->object($accepted);
        $id = $job['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new TextToSpeechFailed('The VoiceLab API accepted the text but gave no generation ID.');
        }

        $statusUrl = is_string($job['status_url'] ?? null) && $job['status_url'] !== '' ? $job['status_url'] : '/v1/tts/generations/'.rawurlencode($id);
        $generation = $this->api->poll($statusUrl);

        if ($generation['status'] === 'failed') {
            throw new TextToSpeechFailed('The VoiceLab generation '.$id.' failed: '.($this->api->errorCode($generation) ?? 'no reason given').'.');
        }

        if (! is_string($generation['audio_url'] ?? null) || $generation['audio_url'] === '') {
            throw new TextToSpeechFailed('The VoiceLab generation '.$id.' gave no audio URL.');
        }

        return $this->api->download($generation['audio_url']);
    }

    /**
     * Get the voice ID to send: the one set, or the voice of that name (or the first) in the catalog.
     */
    private function voiceId(string $language): string
    {
        if (str_starts_with($this->voice, 'voice_')) {
            return $this->voice;
        }

        $voices = $this->catalog->getJson('/v1/voices', ['language' => $language, 'model_id' => $this->model])['data'] ?? null;
        $voices = is_array($voices) ? array_values(array_filter($voices, fn (mixed $voice): bool => is_array($voice) && is_string($voice['id'] ?? null))) : [];

        $chosen = $this->voice === ''
            ? ($voices[0] ?? null)
            : collect($voices)->first(fn (array $voice): bool => is_string($voice['display_name'] ?? null) && mb_strtolower($voice['display_name']) === mb_strtolower($this->voice));

        if ($chosen === null) {
            throw new TextToSpeechFailed('VoiceLab lists no '.$language.' voice of the '.$this->model.' model named ['.$this->voice.']. Set AISHA_TTS_VOICE to a voice_… ID or to a name from GET /v1/voices.');
        }

        return $chosen['id'];
    }

    private function wav(string $audio): string
    {
        if (! str_starts_with($audio, 'RIFF') || substr($audio, 8, 4) !== 'WAVE') {
            throw new TextToSpeechFailed('The VoiceLab API gave no WAV audio.');
        }

        return $audio;
    }
}
