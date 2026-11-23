<?php

use App\Enums\SpeechPause;
use App\Speech\GeminiTextToSpeech;
use App\Speech\SpeakingOptions;
use App\Speech\TextToSpeechFailed;
use App\Speech\TextToSpeechRateLimited;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function geminiVoice(): GeminiTextToSpeech
{
    return new GeminiTextToSpeech(apiKey: 'test-gemini-key', model: 'gemini-3.8-flash-tts', voice: 'Kore', timeoutSeconds: 60);
}

/**
 * A completed voice answer, as the Interactions endpoint gives it.
 *
 * @return array<string, mixed>
 */
function spoken(string $audio): array
{
    return [
        'id' => 'interactions/xyz',
        'status' => 'completed',
        'steps' => [
            ['id' => 'step_000', 'type' => 'user_input', 'content' => []],
            ['id' => 'step_001', 'type' => 'model_output', 'content' => [['type' => 'audio', 'mime_type' => 'audio/wav', 'data' => base64_encode($audio)]]],
        ],
    ];
}

const A_WAV = "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\xc0\x5d\x00\x00\x80\xbb\x00\x00\x02\x00\x10\x00data\x00\x00\x00\x00";

test('it asks for the syllables read slowly with long pauses between them, in the chosen voice, as a WAV', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(spoken(A_WAV))]);

    geminiVoice()->speak(['uy', 'ga'], new SpeakingOptions(slowly: true, pauseBetweenParts: SpeechPause::Long));

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        expect($request->method())->toBe('POST')
            ->and($request->url())->toBe('https://generativelanguage.googleapis.com/v1beta/interactions')
            ->and($request->header('x-goog-api-key'))->toBe(['test-gemini-key'])
            ->and($request->data())->toBe([
                'model' => 'gemini-3.8-flash-tts',
                'input' => [[
                    'type' => 'user_input',
                    'content' => [[
                        'type' => 'text',
                        'text' => 'uy <long pause> ga',
                        'annotations' => [['type' => 'speech_metadata', 'style' => 'speaking slowly and clearly, warm, for a young child']],
                    ]],
                ]],
                'response_format' => ['type' => 'audio', 'mime_type' => 'audio/wav'],
                'generation_config' => ['speech_config' => [['voice' => 'Kore']]],
            ]);

        return true;
    });
});

test('at a normal pace it reads clearly, with short pauses', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(spoken(A_WAV))]);

    geminiVoice()->speak(['Ali', 'uyga'], new SpeakingOptions);

    Http::assertSent(fn (Request $request): bool => $request['input'][0]['content'][0]['text'] === 'Ali <short pause> uyga'
        && $request['input'][0]['content'][0]['annotations'][0]['style'] === 'speaking clearly, warm, for a young child');
});

test('it gives back the WAV the service made', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(spoken(A_WAV))]);

    expect(geminiVoice()->speak(['uyga'], new SpeakingOptions(slowly: true)))->toBe(A_WAV);
});

test('a service failure, a timeout or an answer without a WAV is a text-to-speech failure', function (Closure $answer) {
    Http::fake(['generativelanguage.googleapis.com/*' => $answer]);

    geminiVoice()->speak(['uyga'], new SpeakingOptions);
})->with([
    'a server error' => fn () => fn () => Http::response(['error' => ['message' => 'Internal']], 500),
    'a refused key' => fn () => fn () => Http::response(['error' => ['message' => 'API key not valid']], 403),
    'a timeout' => fn () => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
    'not JSON' => fn () => fn () => Http::response('<html>Bad gateway</html>', 200),
    'no audio' => fn () => fn () => Http::response(['id' => 'interactions/xyz', 'status' => 'completed', 'steps' => []]),
    'audio that is not base64' => fn () => fn () => Http::response(['status' => 'completed', 'steps' => [['type' => 'model_output', 'content' => [['type' => 'audio', 'data' => '***not base64***']]]]]),
    'audio that is not a WAV' => fn () => fn () => Http::response(spoken('just some bytes')),
    'an unfinished interaction' => fn () => fn () => Http::response(['id' => 'interactions/xyz', 'status' => 'in_progress', 'steps' => []]),
])->throws(TextToSpeechFailed::class);

test('a rate-limit or quota answer is a rate limit, with how long the service asks to wait', function (Closure $answer, ?int $retryAfterSeconds) {
    Http::fake(['generativelanguage.googleapis.com/*' => $answer]);

    try {
        geminiVoice()->speak(['uyga'], new SpeakingOptions);
        $this->fail('The rate limit was not reported.');
    } catch (TextToSpeechRateLimited $rateLimited) {
        expect($rateLimited->retryAfterSeconds)->toBe($retryAfterSeconds);
    }
})->with([
    'a 429 with Retry-After in seconds' => [fn () => fn () => Http::response(['error' => ['code' => 429, 'message' => 'Rate limit exceeded', 'status' => 'RESOURCE_EXHAUSTED']], 429, ['Retry-After' => '42']), 42],
    'a 429 with the wait in its details' => [fn () => fn () => Http::response(['error' => [
        'code' => 429,
        'status' => 'RESOURCE_EXHAUSTED',
        'details' => [['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => '31.5s']],
    ]], 429), 32],
    'a 429 that says nothing of a wait' => [fn () => fn () => Http::response(['error' => ['message' => 'Quota exceeded']], 429), null],
    'a used-up quota answered with another status' => [fn () => fn () => Http::response(['error' => ['code' => 403, 'status' => 'RESOURCE_EXHAUSTED']], 403), null],
]);

test('a rate limit stops at the first request: nothing is retried in the same call', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 429]], 429, ['Retry-After' => '20'])]);

    expect(fn () => geminiVoice()->speak(['uyga'], new SpeakingOptions))->toThrow(TextToSpeechRateLimited::class);
    Http::assertSentCount(1);
});
