<?php

namespace App\Speech;

/**
 * Listens with Aisha (VoiceLab), a speech service built for Uzbek, on its
 * sync endpoint for short audio (POST /api/v1/stt/post/).
 *
 * The clip is uploaded as a file; Aisha checks its format and takes only
 * mp3, wav, ogg and m4a, so the browser records WAV. It answers with the
 * whole transcript and no word timings, so the words come from splitting
 * it. Diarization (which needs 15 seconds of audio), offsets and summaries
 * are not asked for, and the expected text is never sent.
 */
final readonly class AishaSpeechToText implements SpeechToText
{
    /**
     * The file extension Aisha takes for each format, by the clip's MIME type.
     */
    private const array EXTENSIONS = [
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/wave' => 'wav',
        'audio/vnd.wave' => 'wav',
        'audio/mp4' => 'm4a',
        'video/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/m4a' => 'm4a',
        'audio/aac' => 'm4a',
        'audio/ogg' => 'ogg',
        'audio/opus' => 'ogg',
        'application/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/webm' => 'webm',
        'video/webm' => 'webm',
    ];

    private AishaApi $api;

    public function __construct(string $apiKey, int $timeoutSeconds)
    {
        $this->api = new AishaApi($apiKey, $timeoutSeconds, SpeechToTextFailed::class);
    }

    public function transcribe(string $audio, string $mimeType, string $expectedText, string $language): array
    {
        $answer = $this->api->post('/api/v1/stt/post/', [
            'language' => $language,
            'has_diarization' => 'false',
        ], ['name' => 'audio', 'contents' => $audio, 'filename' => 'clip.'.$this->extension($mimeType)]);

        if (! array_key_exists('transcript', $answer) || ! (is_string($answer['transcript']) || $answer['transcript'] === null)) {
            throw new SpeechToTextFailed('The Aisha API gave no transcript.');
        }

        return RecognisedWord::inTranscript((string) $answer['transcript']);
    }

    /**
     * Get the file extension for the clip's format, without its codec.
     */
    private function extension(string $mimeType): string
    {
        return self::EXTENSIONS[strtolower(trim(explode(';', $mimeType)[0]))] ?? 'wav';
    }
}
