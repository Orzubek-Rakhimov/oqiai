<?php

use App\Enums\SpeechPause;
use App\Speech\AishaTextToSpeech;
use App\Speech\SpeakingOptions;
use App\Speech\TextToSpeechFailed;
use App\Speech\TextToSpeechRateLimited;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

const VOICELAB_TTS_URL = 'https://api.voicelab.uz/v1/tts';

const VOICELAB_GENERATION_URL = 'https://api.voicelab.uz/v1/tts/generations/gen_01JTEST';

const VOICELAB_AUDIO_URL = 'https://storage.example/signed/gen_01JTEST.wav?signature=abc';

const VOICELAB_VOICES_URL = 'https://api.voicelab.uz/v1/voices*';

const VOICELAB_WAV = "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\xc0\x5d\x00\x00\x80\xbb\x00\x00\x02\x00\x10\x00data\x00\x00\x00\x00";

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake(syncWithCarbon: true);
});

function aishaVoice(string $voice = 'voice_01JUZBEK', string $baseUrl = 'https://api.voicelab.uz'): AishaTextToSpeech
{
    return new AishaTextToSpeech(apiKey: 'vlk_test-key', baseUrl: $baseUrl, model: 'lison', voice: $voice, slowSpeed: 0.8, timeoutSeconds: 60);
}

/**
 * VoiceLab voices the text within the request and answers with the WAV.
 */
function aishaSpeaks(string $audio = VOICELAB_WAV): void
{
    Http::fake([VOICELAB_TTS_URL => Http::response($audio, 200, ['Content-Type' => 'audio/wav'])]);
}

/**
 * The JSON body sent to voice the text.
 *
 * @return array<string, mixed>
 */
function aishaVoiceBody(): array
{
    return Http::recorded(fn (Request $request): bool => $request->url() === VOICELAB_TTS_URL)->first()[0]->data();
}

test('it asks for the syllables, then the word, read slowly with full stops between them, in the chosen model and voice', function () {
    aishaSpeaks();

    aishaVoice()->speak(['ku', 'tub', 'xo', 'na', 'ga', 'kutubxonaga'], new SpeakingOptions(slowly: true, pauseBetweenParts: SpeechPause::Long));

    Http::assertSent(function (Request $request): bool {
        expect($request->method())->toBe('POST')
            ->and($request->url())->toBe(VOICELAB_TTS_URL)
            ->and($request->header('Authorization'))->toBe(['Bearer vlk_test-key'])
            ->and($request->header('Content-Type'))->toBe(['application/json'])
            ->and(Str::isUuid($request->header('Idempotency-Key')[0] ?? ''))->toBeTrue()
            ->and($request->body())->toContain('"speed":0.8');

        return true;
    });
    expect(aishaVoiceBody())->toBe([
        'text' => 'ku. tub. xo. na. ga. kutubxonaga.',
        'language' => 'uz',
        'voice_id' => 'voice_01JUZBEK',
        'model_id' => 'lison',
        'speed' => 0.8,
    ]);
});

test('at a normal pace it reads at the usual speed, with commas for short pauses', function () {
    aishaSpeaks();

    aishaVoice()->speak(['Ali', 'uyga'], new SpeakingOptions);

    expect(aishaVoiceBody())->toMatchArray(['text' => 'Ali, uyga.', 'speed' => 1.0]);
});

test('a WAV made within the request is given back from that one request', function () {
    aishaSpeaks();

    expect(aishaVoice()->speak(['uyga'], new SpeakingOptions(slowly: true)))->toBe(VOICELAB_WAV);

    Http::assertSentCount(1);
});

test('a generation still being made is polled, then its WAV is downloaded without the key', function () {
    Http::fake([
        VOICELAB_TTS_URL => Http::response(['id' => 'gen_01JTEST', 'model_id' => 'lison', 'status' => 'queued', 'status_url' => '/v1/tts/generations/gen_01JTEST', 'request_id' => 'req_01'], 202),
        VOICELAB_GENERATION_URL => Http::sequence([
            Http::response(['id' => 'gen_01JTEST', 'status' => 'processing'], 200, ['Retry-After' => '2']),
            Http::response(['id' => 'gen_01JTEST', 'status' => 'completed', 'audio_url' => VOICELAB_AUDIO_URL, 'request_id' => 'req_02']),
        ]),
        VOICELAB_AUDIO_URL => Http::response(VOICELAB_WAV, 200, ['Content-Type' => 'audio/wav']),
    ]);

    expect(aishaVoice()->speak(['uyga'], new SpeakingOptions))->toBe(VOICELAB_WAV);

    Http::assertSentCount(4);
    Http::assertSent(fn (Request $request): bool => $request->url() === VOICELAB_GENERATION_URL && $request->header('Authorization') === ['Bearer vlk_test-key']);
    Http::assertSent(fn (Request $request): bool => $request->url() === VOICELAB_AUDIO_URL && ! $request->hasHeader('Authorization'));
    Sleep::assertSequence([Sleep::for(2)->seconds()]);
});

test('a voice given by name is looked up in the Uzbek catalog of the model', function () {
    Http::fake([
        VOICELAB_VOICES_URL => Http::response(['data' => [
            ['id' => 'voice_01JOTHER', 'display_name' => 'Lison Neutral', 'language' => 'uz', 'kind' => 'system'],
            ['id' => 'voice_01JGULNOZA', 'display_name' => 'Gulnoza', 'language' => 'uz', 'kind' => 'system'],
        ], 'request_id' => 'req_01']),
        VOICELAB_TTS_URL => Http::response(VOICELAB_WAV, 200, ['Content-Type' => 'audio/wav']),
    ]);

    aishaVoice('gulnoza')->speak(['uyga'], new SpeakingOptions);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && Str::before($request->url(), '?') === 'https://api.voicelab.uz/v1/voices'
        && $request->data() === ['language' => 'uz', 'model_id' => 'lison']
        && $request->header('Authorization') === ['Bearer vlk_test-key']);
    expect(aishaVoiceBody()['voice_id'])->toBe('voice_01JGULNOZA');
});

test('without a voice it takes the first the catalog lists, and an unknown voice is a failure', function () {
    Http::fake([
        VOICELAB_VOICES_URL => Http::response(['data' => [['id' => 'voice_01JFIRST', 'display_name' => 'Lison Neutral']]]),
        VOICELAB_TTS_URL => Http::response(VOICELAB_WAV, 200, ['Content-Type' => 'audio/wav']),
    ]);

    aishaVoice('')->speak(['uyga'], new SpeakingOptions);

    expect(aishaVoiceBody()['voice_id'])->toBe('voice_01JFIRST')
        ->and(fn () => aishaVoice('Nobody')->speak(['uyga'], new SpeakingOptions))->toThrow(TextToSpeechFailed::class, 'AISHA_TTS_VOICE');
});

test('the base URL is a setting', function () {
    Http::fake(['https://voicelab.example/v1/tts' => Http::response(VOICELAB_WAV, 200, ['Content-Type' => 'audio/wav'])]);

    expect(aishaVoice(baseUrl: 'https://voicelab.example')->speak(['uyga'], new SpeakingOptions))->toBe(VOICELAB_WAV);
});

test('more than VoiceLab takes in one request is refused before calling it', function () {
    Http::fake();

    expect(fn () => aishaVoice()->speak([str_repeat('a', 5000), 'b'], new SpeakingOptions))
        ->toThrow(TextToSpeechFailed::class, '5000');
    Http::assertNothingSent();
});

test('a service failure, a timeout or an answer without a WAV is a text-to-speech failure', function (Closure $voicing, Closure $generation, Closure $download) {
    Http::fake([VOICELAB_TTS_URL => $voicing, VOICELAB_GENERATION_URL => $generation, VOICELAB_AUDIO_URL => $download]);

    aishaVoice()->speak(['uyga'], new SpeakingOptions);
})->with([
    'invalid input' => [fn () => fn () => Http::response(['message' => 'Invalid.', 'error' => ['code' => 'validation_error', 'fields' => ['speed' => 'Too fast.']]], 422), fn () => fn () => Http::response([]), fn () => fn () => Http::response(VOICELAB_WAV)],
    'an unavailable voice' => [fn () => fn () => Http::response(['error' => ['code' => 'voice_unavailable']], 404), fn () => fn () => Http::response([]), fn () => fn () => Http::response(VOICELAB_WAV)],
    'the service unavailable' => [fn () => fn () => Http::response(['error' => ['code' => 'service_unavailable']], 503), fn () => fn () => Http::response([]), fn () => fn () => Http::response(VOICELAB_WAV)],
    'a timeout' => [fn () => fn () => throw new ConnectionException('cURL error 28: Operation timed out'), fn () => fn () => Http::response([]), fn () => fn () => Http::response(VOICELAB_WAV)],
    'a 200 that is not a WAV' => [fn () => fn () => Http::response('<html>Bad gateway</html>', 200), fn () => fn () => Http::response([]), fn () => fn () => Http::response(VOICELAB_WAV)],
    'a 202 without a job' => [fn () => fn () => Http::response(['status' => 'queued'], 202), fn () => fn () => Http::response([]), fn () => fn () => Http::response(VOICELAB_WAV)],
    'a failed generation' => [fn () => fn () => Http::response(['id' => 'gen_01JTEST', 'status' => 'queued'], 202), fn () => fn () => Http::response(['id' => 'gen_01JTEST', 'status' => 'failed', 'error' => ['code' => 'generation_failed']]), fn () => fn () => Http::response(VOICELAB_WAV)],
    'a completed generation without audio' => [fn () => fn () => Http::response(['id' => 'gen_01JTEST', 'status' => 'queued'], 202), fn () => fn () => Http::response(['id' => 'gen_01JTEST', 'status' => 'completed']), fn () => fn () => Http::response(VOICELAB_WAV)],
    'a failed download' => [fn () => fn () => Http::response(['id' => 'gen_01JTEST', 'status' => 'queued'], 202), fn () => fn () => Http::response(['id' => 'gen_01JTEST', 'status' => 'completed', 'audio_url' => VOICELAB_AUDIO_URL]), fn () => fn () => Http::response('Expired', 403)],
    'a download timeout' => [fn () => fn () => Http::response(['id' => 'gen_01JTEST', 'status' => 'queued'], 202), fn () => fn () => Http::response(['id' => 'gen_01JTEST', 'status' => 'completed', 'audio_url' => VOICELAB_AUDIO_URL]), fn () => fn () => throw new ConnectionException('cURL error 28')],
    'a download that is not a WAV' => [fn () => fn () => Http::response(['id' => 'gen_01JTEST', 'status' => 'queued'], 202), fn () => fn () => Http::response(['id' => 'gen_01JTEST', 'status' => 'completed', 'audio_url' => VOICELAB_AUDIO_URL]), fn () => fn () => Http::response('ID3 some mp3 bytes')],
    'a generation still pending when the time is up' => [fn () => fn () => Http::response(['id' => 'gen_01JTEST', 'status' => 'queued'], 202), fn () => fn () => Http::response(['id' => 'gen_01JTEST', 'status' => 'processing']), fn () => fn () => Http::response(VOICELAB_WAV)],
])->throws(TextToSpeechFailed::class);

test('a rate limit or used-up credits is a rate limit, with how long the service asks to wait', function (Closure $answer, ?int $retryAfterSeconds) {
    Http::fake([VOICELAB_TTS_URL => $answer]);

    try {
        aishaVoice()->speak(['uyga'], new SpeakingOptions);
        $this->fail('The rate limit was not reported.');
    } catch (TextToSpeechRateLimited $rateLimited) {
        expect($rateLimited->retryAfterSeconds)->toBe($retryAfterSeconds);
    }

    Http::assertSentCount(1);
})->with([
    'a 429 with Retry-After in seconds' => [fn () => fn () => Http::response(['error' => ['code' => 'rate_limited']], 429, ['Retry-After' => '42']), 42],
    'an overload that says nothing of a wait' => [fn () => fn () => Http::response(['error' => ['code' => 'overloaded']], 429), null],
    'the credits used up' => [fn () => fn () => Http::response(['message' => 'Add credits.', 'error' => ['code' => 'insufficient_credits']], 402), null],
    'the key credit limit reached' => [fn () => fn () => Http::response(['error' => ['code' => 'api_key_credit_limit']], 402, ['Retry-After' => '3600']), 3600],
]);

test('a refused key or a missing permission is logged naming the key type and the permission needed, not as a rate limit', function (string $voice, string $url, int $status, string $code, string $permission) {
    Log::spy();
    Http::fake([$url => Http::response(['message' => 'Refused.', 'error' => ['code' => $code], 'request_id' => 'req_01'], $status)]);

    try {
        aishaVoice($voice)->speak(['uyga'], new SpeakingOptions);
        $this->fail('The refusal was not reported.');
    } catch (TextToSpeechFailed $failed) {
        expect($failed)->not->toBeInstanceOf(TextToSpeechRateLimited::class)
            ->and($failed->getMessage())->toContain($code);
    }

    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, $code)
        && str_contains($message, 'vlk_')
        && str_contains($message, $permission))->once();
})->with([
    'an invalid key' => ['voice_01JUZBEK', VOICELAB_TTS_URL, 401, 'invalid_api_key', 'Text to speech'],
    'no Text to speech permission' => ['voice_01JUZBEK', VOICELAB_TTS_URL, 403, 'insufficient_scope', 'Text to speech'],
    'no Voices permission for the catalog' => ['Gulnoza', VOICELAB_VOICES_URL, 403, 'insufficient_scope', 'Voices'],
]);

test('without a key it fails before calling VoiceLab', function () {
    Http::fake();

    expect(fn () => (new AishaTextToSpeech(apiKey: '', baseUrl: 'https://api.voicelab.uz', model: 'lison', voice: 'voice_01JUZBEK', slowSpeed: 0.8, timeoutSeconds: 60))->speak(['uyga'], new SpeakingOptions))
        ->toThrow(TextToSpeechFailed::class, 'AISHA_API_KEY');
    Http::assertNothingSent();
});
