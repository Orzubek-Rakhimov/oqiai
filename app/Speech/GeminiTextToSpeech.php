<?php

namespace App\Speech;

/**
 * Voices the Syllable help clips with a Gemini speech model
 * (gemini-3.8-flash-tts, or gemini-3.8-flash-lite-tts as the cheaper
 * fallback) on the Interactions endpoint.
 *
 * The pace goes in the speech_metadata style annotation and the pauses
 * between the parts as inline <short pause> / <long pause> tags. The model
 * finds the language from the text, so the request names none. It answers
 * with a WAV file (24 kHz, mono, 16-bit), which is saved as it is.
 */
final readonly class GeminiTextToSpeech implements TextToSpeech
{
    private GeminiInteractions $interactions;

    public function __construct(string $apiKey, private string $model, private string $voice, int $timeoutSeconds)
    {
        $this->interactions = new GeminiInteractions($apiKey, $timeoutSeconds, TextToSpeechFailed::class);
    }

    public function speak(array $parts, SpeakingOptions $options): string
    {
        $outputs = $this->interactions->outputs([
            'model' => $this->model,
            'input' => [[
                'type' => 'user_input',
                'content' => [[
                    'type' => 'text',
                    'text' => implode(' <'.$options->pauseBetweenParts->value.' pause> ', $parts),
                    'annotations' => [['type' => 'speech_metadata', 'style' => $this->style($options)]],
                ]],
            ]],
            'response_format' => ['type' => 'audio', 'mime_type' => 'audio/wav'],
            'generation_config' => ['speech_config' => [['voice' => $this->voice]]],
        ]);

        $audio = array_values(array_filter($outputs, fn (array $output): bool => ($output['type'] ?? null) === 'audio'));
        $data = end($audio)['data'] ?? null;
        $wav = is_string($data) ? base64_decode($data, strict: true) : false;

        if ($wav === false || ! str_starts_with($wav, 'RIFF') || substr($wav, 8, 4) !== 'WAVE') {
            throw new TextToSpeechFailed('The Gemini API gave no WAV audio.');
        }

        return $wav;
    }

    /**
     * Describe how the voice reads, for the style annotation.
     */
    private function style(SpeakingOptions $options): string
    {
        return ($options->slowly ? 'speaking slowly and clearly' : 'speaking clearly').', warm, for a young child';
    }
}
