<?php

use App\Speech\GeminiSpeechToText;
use App\Speech\RecognisedWord;
use App\Speech\SpeechToTextFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const GEMINI_INTERACTIONS_URL = 'https://generativelanguage.googleapis.com/v1beta/interactions';

beforeEach(function () {
    Http::preventStrayRequests();
});

function geminiListener(): GeminiSpeechToText
{
    return new GeminiSpeechToText(apiKey: 'test-gemini-key', model: 'gemini-3.5-transcribe', timeoutSeconds: 20);
}

/**
 * A completed transcription, as the Interactions endpoint answers it.
 *
 * @param  list<array<string, mixed>>  $annotations
 * @return array<string, mixed>
 */
function transcription(string $text, array $annotations = []): array
{
    return [
        'id' => 'interactions/abc123',
        'status' => 'completed',
        'steps' => [[
            'id' => 'step_001',
            'type' => 'model_output',
            'content' => [['type' => 'text', 'text' => $text, 'annotations' => $annotations]],
        ]],
    ];
}

test('it sends the clip inline for a verbatim Uzbek transcription with word timings, and nothing of the Story', function () {
    Http::fake([GEMINI_INTERACTIONS_URL => Http::response(transcription('Ali uyga'))]);

    geminiListener()->transcribe('clip-bytes', 'audio/webm;codecs=opus', 'Ali uyga keldi.', 'uz');

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        expect($request->method())->toBe('POST')
            ->and($request->url())->toBe(GEMINI_INTERACTIONS_URL)
            ->and($request->header('x-goog-api-key'))->toBe(['test-gemini-key'])
            ->and($request->data())->toBe([
                'model' => 'models/gemini-3.5-transcribe',
                'input' => [['type' => 'audio', 'data' => base64_encode('clip-bytes'), 'mime_type' => 'audio/webm']],
                'generation_config' => [
                    'transcription_config' => [
                        'language_codes' => ['uz-UZ'],
                        'mode' => ['type' => 'verbatim', 'timestamp_granularities' => ['word']],
                    ],
                ],
            ])
            ->and($request->body())->not->toContain('keldi')
            ->and($request->body())->not->toContain('custom_vocabulary')
            ->and($request->body())->not->toContain('smart');

        return true;
    });
});

test('it names an iPhone clip as m4a, the name the service knows', function () {
    Http::fake([GEMINI_INTERACTIONS_URL => Http::response(transcription('Ali'))]);

    geminiListener()->transcribe('clip-bytes', 'audio/mp4', 'Ali', 'uz');

    Http::assertSent(fn (Request $request): bool => $request['input'][0]['mime_type'] === 'audio/m4a');
});

test('it names the WAV the browser records as audio/wav, however the content is detected', function (string $mimeType) {
    Http::fake([GEMINI_INTERACTIONS_URL => Http::response(transcription('Ali'))]);

    geminiListener()->transcribe('RIFF-clip-bytes', $mimeType, 'Ali', 'uz');

    Http::assertSent(fn (Request $request): bool => $request['input'][0]['mime_type'] === 'audio/wav');
})->with(['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave']);

test('it hears the words of the word timings in order, with their times', function () {
    Http::fake([GEMINI_INTERACTIONS_URL => Http::response(transcription('Ali uyga', [
        ['type' => 'word_info', 'text' => 'Ali', 'start_offset' => '0.100s', 'end_offset' => '0.400s'],
        ['type' => 'word_info', 'text' => 'uyga', 'start_offset' => '0.550s', 'end_offset' => '1.200s'],
    ]))]);

    $words = geminiListener()->transcribe('clip-bytes', 'audio/webm', 'Ali uyga keldi.', 'uz');

    expect($words)->toEqual([
        new RecognisedWord('Ali', startSeconds: 0.1, endSeconds: 0.4),
        new RecognisedWord('uyga', startSeconds: 0.55, endSeconds: 1.2),
    ]);
});

test('without word timings it hears the words of the transcript in order', function () {
    Http::fake([GEMINI_INTERACTIONS_URL => Http::response(transcription('Ali  uyga, keldi'))]);

    expect(geminiListener()->transcribe('clip-bytes', 'audio/webm', 'Ali uyga keldi.', 'uz'))->toEqual([
        new RecognisedWord('Ali'),
        new RecognisedWord('uyga,'),
        new RecognisedWord('keldi'),
    ]);
});

test('an empty transcript means nothing was heard', function () {
    Http::fake([GEMINI_INTERACTIONS_URL => Http::response(transcription(''))]);

    expect(geminiListener()->transcribe('clip-bytes', 'audio/webm', 'Ali', 'uz'))->toBe([]);
});

test('a completed answer with no text means nothing was heard', function () {
    Http::fake([GEMINI_INTERACTIONS_URL => Http::response(['id' => 'interactions/abc', 'status' => 'completed', 'steps' => []])]);

    expect(geminiListener()->transcribe('clip-bytes', 'audio/webm', 'Ali', 'uz'))->toBe([]);
});

test('a service failure, a timeout or an answer it cannot read is a speech-to-text failure', function (Closure $answer) {
    Http::fake([GEMINI_INTERACTIONS_URL => $answer]);

    geminiListener()->transcribe('clip-bytes', 'audio/webm', 'Ali', 'uz');
})->with([
    'a server error' => fn () => fn () => Http::response(['error' => ['message' => 'Internal']], 500),
    'a refused key' => fn () => fn () => Http::response(['error' => ['message' => 'API key not valid']], 403),
    'too many requests' => fn () => fn () => Http::response([], 429),
    'a timeout' => fn () => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
    'not JSON' => fn () => fn () => Http::response('<html>Bad gateway</html>', 200),
    'an unfinished interaction' => fn () => fn () => Http::response(['id' => 'interactions/abc', 'status' => 'failed', 'steps' => []]),
])->throws(SpeechToTextFailed::class);
