<?php

namespace App\Speech;

/**
 * Listens with Aisha (VoiceLab), a speech service built for Uzbek, through
 * VoiceLab's developer API (POST /v1/stt, then GET /v1/stt/transcriptions/{id}).
 *
 * The clip is uploaded as a file with a fresh idempotency key. VoiceLab
 * checks the container and codec and takes MP3, WAV, M4A/AAC, OGG/Opus,
 * WebM/Opus and FLAC; the browser records WAV. Every upload is answered 202
 * with a job, which is polled until it is done, within the timeout. The
 * words come from the segments' word timings when VoiceLab gives them, else
 * from splitting the text. "No speech detected" is nothing heard. Speaker
 * labels are not asked for, and the expected text is never sent.
 */
final readonly class AishaSpeechToText implements SpeechToText
{
    /**
     * The file extension for each format, by the clip's MIME type.
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
        'audio/flac' => 'flac',
    ];

    private const string NO_SPEECH = 'no_speech_detected';

    private AishaApi $api;

    public function __construct(string $apiKey, string $baseUrl, int $timeoutSeconds)
    {
        $this->api = new AishaApi($apiKey, $baseUrl, $timeoutSeconds, 'Speech to text permission at level Access', SpeechToTextFailed::class);
    }

    public function transcribe(string $audio, string $mimeType, string $expectedText, string $language): array
    {
        $response = $this->api->postMultipart('/v1/stt', [
            'language' => $language,
            'include_speakers' => 'false',
        ], ['name' => 'audio', 'contents' => $audio, 'filename' => 'clip.'.$this->extension($mimeType)], [self::NO_SPEECH]);

        if ($this->api->errorCode($response) === self::NO_SPEECH) {
            return [];
        }

        $id = $this->api->object($response)['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new SpeechToTextFailed('The VoiceLab API accepted the clip but gave no transcription ID.');
        }

        $job = $this->api->poll('/v1/stt/transcriptions/'.rawurlencode($id));

        if ($job['status'] === 'failed') {
            if ($this->api->errorCode($job) === self::NO_SPEECH) {
                return [];
            }

            throw new SpeechToTextFailed('The VoiceLab transcription '.$id.' failed: '.($this->api->errorCode($job) ?? 'no reason given').'.');
        }

        if (! array_key_exists('transcript', $job) || ! (is_string($job['transcript']) || $job['transcript'] === null)) {
            throw new SpeechToTextFailed('The VoiceLab API gave no transcript.');
        }

        $segments = is_array($job['segments'] ?? null) ? $job['segments'] : [];

        if ($segments === []) {
            return RecognisedWord::inTranscript((string) $job['transcript']);
        }

        return array_merge(...array_map($this->segmentWords(...), array_values($segments)));
    }

    /**
     * Get the words of one segment: its timed words when VoiceLab gives them, else its text split.
     *
     * @return list<RecognisedWord>
     */
    private function segmentWords(mixed $segment): array
    {
        if (! is_array($segment)) {
            return [];
        }

        $words = is_array($segment['words'] ?? null) ? $segment['words'] : [];

        if ($words === []) {
            return RecognisedWord::inTranscript(is_string($segment['text'] ?? null) ? $segment['text'] : '');
        }

        $recognised = [];

        foreach ($words as $word) {
            $text = is_array($word) && is_string($word['text'] ?? null) ? trim($word['text']) : '';

            if ($text !== '') {
                $recognised[] = new RecognisedWord($text, $this->seconds($word['start_ms'] ?? null), $this->seconds($word['end_ms'] ?? null));
            }
        }

        return $recognised;
    }

    private function seconds(mixed $milliseconds): ?float
    {
        return is_int($milliseconds) || is_float($milliseconds) ? $milliseconds / 1000 : null;
    }

    /**
     * Get the file extension for the clip's format, without its codec.
     */
    private function extension(string $mimeType): string
    {
        return self::EXTENSIONS[strtolower(trim(explode(';', $mimeType)[0]))] ?? 'wav';
    }
}
