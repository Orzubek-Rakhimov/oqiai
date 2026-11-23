<?php

use App\Enums\SpeechPause;
use App\Speech\AishaTextToSpeech;
use App\Speech\SpeakingOptions;
use App\Speech\TextToSpeechFailed;
use App\Speech\TextToSpeechRateLimited;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const AISHA_TTS_URL = 'https://back.aisha.group/api/v1/tts/post/';

const AISHA_AUDIO_URL = 'https://back.aisha.group/media/tts_audios/request-id.wav';

const AISHA_WAV = "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\x80\x3e\x00\x00\x00\x7d\x00\x00\x02\x00\x10\x00data\x00\x00\x00\x00";

beforeEach(function () {
    Http::preventStrayRequests();
});

function aishaVoice(): AishaTextToSpeech
{
    return new AishaTextToSpeech(apiKey: 'test-aisha-key', model: 'Gulnoza', mood: 'Cheerful', slowSpeed: 0.8, timeoutSeconds: 60);
}

/**
 * Aisha voices the text and keeps the WAV for download.
 */
function aishaSpeaks(string $audio = AISHA_WAV): void
{
    Http::fake([
        AISHA_TTS_URL => Http::response(['audio_path' => '/media/tts_audios/request-id.wav'], 201),
        AISHA_AUDIO_URL => Http::response($audio, 200, ['Content-Type' => 'audio/wav']),
    ]);
}

/**
 * The fields of the form sent to voice the text.
 *
 * @return array<string, string>
 */
function aishaVoiceFields(): array
{
    $sent = Http::recorded(fn (Request $request): bool => $request->url() === AISHA_TTS_URL)->first()[0];

    return collect($sent->data())->mapWithKeys(fn (array $part): array => [$part['name'] => $part['contents']])->all();
}

test('it asks for the syllables, then the word, read slowly with full stops between them, in the chosen voice and mood', function () {
    aishaSpeaks();

    aishaVoice()->speak(['ku', 'tub', 'xo', 'na', 'ga', 'kutubxonaga'], new SpeakingOptions(slowly: true, pauseBetweenParts: SpeechPause::Long));

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== AISHA_TTS_URL) {
            return false;
        }

        expect($request->method())->toBe('POST')
            ->and($request->header('X-Api-Key'))->toBe(['test-aisha-key'])
            ->and($request->isMultipart())->toBeTrue();

        return true;
    });
    expect(aishaVoiceFields())->toBe([
        'transcript' => 'ku. tub. xo. na. ga. kutubxonaga.',
        'language' => 'uz',
        'model' => 'Gulnoza',
        'mood' => 'Cheerful',
        'speed' => '0.8',
    ]);
});

test('at a normal pace it reads at the usual speed, with commas for short pauses', function () {
    aishaSpeaks();

    aishaVoice()->speak(['Ali', 'uyga'], new SpeakingOptions);

    expect(aishaVoiceFields())->toMatchArray(['transcript' => 'Ali, uyga.', 'speed' => '1']);
});

test('it downloads the WAV Aisha made and gives it back, in two requests', function () {
    aishaSpeaks();

    expect(aishaVoice()->speak(['uyga'], new SpeakingOptions(slowly: true)))->toBe(AISHA_WAV);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === AISHA_AUDIO_URL
        && $request->header('X-Api-Key') === ['test-aisha-key']);
});

test('more than Aisha takes in one request is refused before calling it', function () {
    Http::fake();

    expect(fn () => aishaVoice()->speak([str_repeat('a', 1000), 'b'], new SpeakingOptions))
        ->toThrow(TextToSpeechFailed::class, '1000');
    Http::assertNothingSent();
});

test('a service failure, a timeout or an answer without a WAV is a text-to-speech failure', function (Closure $voicing, Closure $download) {
    Http::fake([AISHA_TTS_URL => $voicing, AISHA_AUDIO_URL => $download]);

    aishaVoice()->speak(['uyga'], new SpeakingOptions);
})->with([
    'invalid input' => [fn () => fn () => Http::response(['detail' => 'Invalid'], 400), fn () => fn () => Http::response(AISHA_WAV)],
    'a refused key' => [fn () => fn () => Http::response(['detail' => 'Unauthorized'], 401), fn () => fn () => Http::response(AISHA_WAV)],
    'no access' => [fn () => fn () => Http::response(['detail' => 'Forbidden'], 403), fn () => fn () => Http::response(AISHA_WAV)],
    'the service unavailable' => [fn () => fn () => Http::response(['detail' => 'Unavailable'], 503), fn () => fn () => Http::response(AISHA_WAV)],
    'a timeout' => [fn () => fn () => throw new ConnectionException('cURL error 28: Operation timed out'), fn () => fn () => Http::response(AISHA_WAV)],
    'not JSON' => [fn () => fn () => Http::response('<html>Bad gateway</html>', 201), fn () => fn () => Http::response(AISHA_WAV)],
    'no audio path' => [fn () => fn () => Http::response(['id' => 7, 'status' => 'PENDING'], 202), fn () => fn () => Http::response(AISHA_WAV)],
    'a failed download' => [fn () => fn () => Http::response(['audio_path' => '/media/tts_audios/request-id.wav'], 201), fn () => fn () => Http::response('Not found', 404)],
    'a download timeout' => [fn () => fn () => Http::response(['audio_path' => '/media/tts_audios/request-id.wav'], 201), fn () => fn () => throw new ConnectionException('cURL error 28')],
    'a download that is not a WAV' => [fn () => fn () => Http::response(['audio_path' => '/media/tts_audios/request-id.wav'], 201), fn () => fn () => Http::response('ID3 some mp3 bytes')],
])->throws(TextToSpeechFailed::class);

test('a rate limit or a used-up balance is a rate limit, with how long the service asks to wait', function (Closure $answer, ?int $retryAfterSeconds) {
    Http::fake([AISHA_TTS_URL => $answer]);

    try {
        aishaVoice()->speak(['uyga'], new SpeakingOptions);
        $this->fail('The rate limit was not reported.');
    } catch (TextToSpeechRateLimited $rateLimited) {
        expect($rateLimited->retryAfterSeconds)->toBe($retryAfterSeconds);
    }

    Http::assertSentCount(1);
})->with([
    'a 429 with Retry-After in seconds' => [fn () => fn () => Http::response(['detail' => 'Throttled'], 429, ['Retry-After' => '42']), 42],
    'a 429 that says nothing of a wait' => [fn () => fn () => Http::response(['detail' => 'Throttled'], 429), null],
    'the balance used up' => [fn () => fn () => Http::response(['detail' => 'Insufficient balance'], 402), null],
]);

test('without a key it fails before calling Aisha', function () {
    Http::fake();

    expect(fn () => (new AishaTextToSpeech(apiKey: '', model: 'Gulnoza', mood: 'Neutral', slowSpeed: 0.8, timeoutSeconds: 60))->speak(['uyga'], new SpeakingOptions))
        ->toThrow(TextToSpeechFailed::class, 'AISHA_API_KEY');
    Http::assertNothingSent();
});
