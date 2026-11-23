<?php

namespace App\Speech;

/**
 * Listens with a Gemini transcription model (gemini-3.5-transcribe) on the
 * Interactions endpoint, in verbatim mode with word timings.
 *
 * Verbatim mode reports what was said, self-corrections and all. Smart mode
 * and a custom vocabulary are never used, and the expected text is never
 * sent: each would smooth a misread word into the right one.
 */
final readonly class GeminiSpeechToText implements SpeechToText
{
    /**
     * The browsers' names for a clip's format, where the Gemini API knows it by another.
     */
    private const array MIME_TYPES = [
        'audio/mp4' => 'audio/m4a',
        'video/mp4' => 'audio/m4a',
        'audio/x-m4a' => 'audio/m4a',
        'video/webm' => 'audio/webm',
        'audio/x-wav' => 'audio/wav',
        'audio/wave' => 'audio/wav',
        'audio/vnd.wave' => 'audio/wav',
    ];

    private GeminiInteractions $interactions;

    public function __construct(string $apiKey, private string $model, int $timeoutSeconds)
    {
        $this->interactions = new GeminiInteractions($apiKey, $timeoutSeconds, SpeechToTextFailed::class);
    }

    public function transcribe(string $audio, string $mimeType, string $expectedText, string $language): array
    {
        $outputs = $this->interactions->outputs([
            'model' => 'models/'.$this->model,
            'input' => [['type' => 'audio', 'data' => base64_encode($audio), 'mime_type' => $this->mimeType($mimeType)]],
            'generation_config' => [
                'transcription_config' => [
                    'language_codes' => [GoogleSpeech::languageCode($language)],
                    'mode' => ['type' => 'verbatim', 'timestamp_granularities' => ['word']],
                ],
            ],
        ]);

        $words = [];
        $transcript = '';

        foreach ($outputs as $output) {
            if (($output['type'] ?? null) !== 'text') {
                continue;
            }

            $transcript .= ' '.(is_string($output['text'] ?? null) ? $output['text'] : '');

            foreach (is_array($output['annotations'] ?? null) ? $output['annotations'] : [] as $annotation) {
                if (is_array($annotation) && ($annotation['type'] ?? null) === 'word_info' && is_string($annotation['text'] ?? null) && trim($annotation['text']) !== '') {
                    $words[] = new RecognisedWord(
                        trim($annotation['text']),
                        startSeconds: GoogleSpeech::seconds($annotation['start_offset'] ?? null),
                        endSeconds: GoogleSpeech::seconds($annotation['end_offset'] ?? null),
                    );
                }
            }
        }

        return $words !== [] ? $words : RecognisedWord::inTranscript($transcript);
    }

    /**
     * Get the clip's format without its codec, by the name the Gemini API knows.
     */
    private function mimeType(string $mimeType): string
    {
        $type = strtolower(trim(explode(';', $mimeType)[0]));

        return self::MIME_TYPES[$type] ?? $type;
    }
}
