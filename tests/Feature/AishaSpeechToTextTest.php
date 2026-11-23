<?php

use App\Speech\AishaSpeechToText;
use App\Speech\RecognisedWord;
use App\Speech\SpeechToTextFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

const VOICELAB_STT_URL = 'https://api.voicelab.uz/v1/stt';

const VOICELAB_STT_JOB_URL = 'https://api.voicelab.uz/v1/stt/transcriptions/stt_01JTEST';

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake(syncWithCarbon: true);
});

function aishaListener(string $baseUrl = 'https://api.voicelab.uz'): AishaSpeechToText
{
    return new AishaSpeechToText(apiKey: 'vlk_test-key', baseUrl: $baseUrl, timeoutSeconds: 20);
}

/**
 * The 202 VoiceLab answers every accepted upload with.
 */
function aishaAccepted(): mixed
{
    return Http::response(['id' => 'stt_01JTEST', 'status' => 'queued', 'request_id' => 'req_01'], 202);
}

/**
 * A finished transcription, as polling it answers.
 *
 * @param  list<array<string, mixed>>  $segments
 * @return array<string, mixed>
 */
function aishaTranscript(string $transcript, array $segments = []): array
{
    return [
        'id' => 'stt_01JTEST', 'status' => 'completed', 'transcript' => $transcript, 'language' => 'uz',
        'duration_ms' => 1420, 'segments' => $segments, 'speakers' => [], 'speakers_available' => false, 'request_id' => 'req_02',
    ];
}

/**
 * Accept the upload, then answer the polls in turn.
 */
function aishaHears(mixed ...$polls): void
{
    Http::fake([
        VOICELAB_STT_URL => aishaAccepted(),
        VOICELAB_STT_JOB_URL => Http::sequence(array_map(fn (mixed $poll): mixed => is_array($poll) ? Http::response($poll) : $poll, $polls)),
    ]);
}

test('it uploads the clip as a WAV file for an Uzbek transcription, with the Bearer key, a fresh idempotency key and nothing of the Story', function () {
    aishaHears(aishaTranscript('Ali uyga'));

    aishaListener()->transcribe('RIFF-clip-bytes', 'audio/x-wav', 'Ali uyga keldi.', 'uz');

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== VOICELAB_STT_URL) {
            return false;
        }

        $parts = collect($request->data())->keyBy('name');

        expect($request->method())->toBe('POST')
            ->and($request->header('Authorization'))->toBe(['Bearer vlk_test-key'])
            ->and(Str::isUuid($request->header('Idempotency-Key')[0] ?? ''))->toBeTrue()
            ->and($request->isMultipart())->toBeTrue()
            ->and($parts->keys()->sort()->values()->all())->toBe(['audio', 'include_speakers', 'language'])
            ->and($parts['audio']['contents'])->toBe('RIFF-clip-bytes')
            ->and($parts['audio']['filename'])->toBe('clip.wav')
            ->and($parts['language']['contents'])->toBe('uz')
            ->and($parts['include_speakers']['contents'])->toBe('false')
            ->and($request->body())->not->toContain('keldi');

        return true;
    });
});

test('it polls the transcription with the same key until it is done, waiting as asked', function () {
    aishaHears(
        ['id' => 'stt_01JTEST', 'status' => 'queued'],
        Http::response(['id' => 'stt_01JTEST', 'status' => 'processing'], 200, ['Retry-After' => '2']),
        aishaTranscript('uyga'),
    );

    expect(aishaListener()->transcribe('clip-bytes', 'audio/wav', 'uyga', 'uz'))->toEqual([new RecognisedWord('uyga')]);

    Http::assertSentCount(4);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === VOICELAB_STT_JOB_URL
        && $request->header('Authorization') === ['Bearer vlk_test-key']);
    Sleep::assertSequence([Sleep::for(1)->second(), Sleep::for(2)->seconds()]);
});

test('the base URL is a setting', function () {
    Http::fake([
        'https://voicelab.example/v1/stt' => aishaAccepted(),
        'https://voicelab.example/v1/stt/transcriptions/stt_01JTEST' => Http::response(aishaTranscript('uyga')),
    ]);

    expect(aishaListener('https://voicelab.example/')->transcribe('clip-bytes', 'audio/wav', 'uyga', 'uz'))->toHaveCount(1);
});

test('the clip gets the file name of its format', function (string $mimeType, string $filename) {
    aishaHears(aishaTranscript('uyga'));

    aishaListener()->transcribe('clip-bytes', $mimeType, 'uyga', 'uz');

    Http::assertSent(fn (Request $request): bool => $request->url() === VOICELAB_STT_URL
        && collect($request->data())->firstWhere('name', 'audio')['filename'] === $filename);
})->with([
    'WAV' => ['audio/wav', 'clip.wav'],
    'WAV, as the content is detected' => ['audio/x-wav', 'clip.wav'],
    'an iPhone recording' => ['audio/mp4', 'clip.m4a'],
    'Ogg' => ['audio/ogg; codecs=opus', 'clip.ogg'],
    'MP3' => ['audio/mpeg', 'clip.mp3'],
    'WebM' => ['audio/webm;codecs=opus', 'clip.webm'],
]);

test('it gives back the timed words of the segments, in order', function () {
    aishaHears(aishaTranscript('Ali uyga keldi.', [
        ['ordinal' => 1, 'start_ms' => 120, 'end_ms' => 900, 'text' => 'Ali uyga', 'words' => [
            ['ordinal' => 1, 'start_ms' => 120, 'end_ms' => 400, 'text' => 'Ali'],
            ['ordinal' => 2, 'start_ms' => 450, 'end_ms' => 900, 'text' => 'uyga'],
        ]],
        ['ordinal' => 2, 'start_ms' => 1000, 'end_ms' => 1320, 'text' => 'keldi.', 'words' => [
            ['ordinal' => 1, 'start_ms' => 1000, 'end_ms' => 1320, 'text' => 'keldi.'],
        ]],
    ]));

    expect(aishaListener()->transcribe('clip-bytes', 'audio/wav', 'Ali uyga keldi.', 'uz'))->toEqual([
        new RecognisedWord('Ali', 0.12, 0.4),
        new RecognisedWord('uyga', 0.45, 0.9),
        new RecognisedWord('keldi.', 1.0, 1.32),
    ]);
});

test('without word timings it splits the segment, or the transcript when there are no segments', function () {
    aishaHears(aishaTranscript('Ali uyga keldi.', [
        ['ordinal' => 1, 'start_ms' => 120, 'end_ms' => 900, 'text' => ' Ali  uyga'],
        ['ordinal' => 2, 'start_ms' => 1000, 'end_ms' => 1320, 'text' => 'keldi.', 'words' => [
            ['ordinal' => 1, 'start_ms' => 1000, 'end_ms' => 1320, 'text' => 'keldi.'],
        ]],
    ]), aishaTranscript(" Ali  uyga\nkeldi. "));

    expect(aishaListener()->transcribe('clip-bytes', 'audio/wav', 'Ali uyga keldi.', 'uz'))->toEqual([
        new RecognisedWord('Ali'),
        new RecognisedWord('uyga'),
        new RecognisedWord('keldi.', 1.0, 1.32),
    ])->and(aishaListener()->transcribe('clip-bytes', 'audio/wav', 'Ali uyga keldi.', 'uz'))->toEqual([
        new RecognisedWord('Ali'),
        new RecognisedWord('uyga'),
        new RecognisedWord('keldi.'),
    ]);
});

test('an empty transcript or no speech detected is nothing heard', function (string $answer) {
    match ($answer) {
        'empty' => aishaHears(aishaTranscript('')),
        'only spaces' => aishaHears(aishaTranscript('  ')),
        'null' => aishaHears([...aishaTranscript(''), 'transcript' => null]),
        'refused as no speech' => Http::fake([VOICELAB_STT_URL => Http::response(['message' => 'No speech.', 'error' => ['code' => 'no_speech_detected'], 'request_id' => 'req_01'], 422)]),
        'a job that failed as no speech' => aishaHears(['id' => 'stt_01JTEST', 'status' => 'failed', 'error' => ['code' => 'no_speech_detected']]),
    };

    expect(aishaListener()->transcribe('clip-bytes', 'audio/wav', 'uyga', 'uz'))->toBe([]);
})->with(['empty', 'only spaces', 'null', 'refused as no speech', 'a job that failed as no speech']);

test('a service failure, a timeout or an unreadable answer is a speech-to-text failure', function (Closure $upload, Closure $poll) {
    Http::fake([VOICELAB_STT_URL => $upload, VOICELAB_STT_JOB_URL => $poll]);

    aishaListener()->transcribe('clip-bytes', 'audio/wav', 'uyga', 'uz');
})->with([
    'a refused format' => [fn () => fn () => Http::response(['message' => 'Unsupported.', 'error' => ['code' => 'unsupported_audio_format']], 415), fn () => fn () => Http::response(aishaTranscript('uyga'))],
    'no credits' => [fn () => fn () => Http::response(['message' => 'No credits.', 'error' => ['code' => 'insufficient_credits']], 402), fn () => fn () => Http::response(aishaTranscript('uyga'))],
    'too many requests' => [fn () => fn () => Http::response(['error' => ['code' => 'rate_limited']], 429, ['Retry-After' => '3']), fn () => fn () => Http::response(aishaTranscript('uyga'))],
    'the service unavailable' => [fn () => fn () => Http::response(['error' => ['code' => 'stt_unavailable']], 503), fn () => fn () => Http::response(aishaTranscript('uyga'))],
    'a timeout' => [fn () => fn () => throw new ConnectionException('cURL error 28: Operation timed out'), fn () => fn () => Http::response(aishaTranscript('uyga'))],
    'an acceptance without a job' => [fn () => fn () => Http::response(['status' => 'queued'], 202), fn () => fn () => Http::response(aishaTranscript('uyga'))],
    'not JSON' => [fn () => fn () => Http::response('<html>Bad gateway</html>', 202), fn () => fn () => Http::response(aishaTranscript('uyga'))],
    'a failed job' => [fn () => fn () => aishaAccepted(), fn () => fn () => Http::response(['id' => 'stt_01JTEST', 'status' => 'failed', 'error' => ['code' => 'invalid_audio']])],
    'a failed poll' => [fn () => fn () => aishaAccepted(), fn () => fn () => Http::response(['error' => ['code' => 'stt_transcription_not_found']], 404)],
    'a poll timeout' => [fn () => fn () => aishaAccepted(), fn () => fn () => throw new ConnectionException('cURL error 28')],
    'no transcript' => [fn () => fn () => aishaAccepted(), fn () => fn () => Http::response(['id' => 'stt_01JTEST', 'status' => 'completed'])],
])->throws(SpeechToTextFailed::class);

test('a transcription still pending when the time is up is a failure, without a second upload', function () {
    aishaHears(...array_fill(0, 40, ['id' => 'stt_01JTEST', 'status' => 'processing']));

    expect(fn () => aishaListener()->transcribe('clip-bytes', 'audio/wav', 'uyga', 'uz'))
        ->toThrow(SpeechToTextFailed::class, 'still processing');
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST'))->toHaveCount(1);
});

test('a refused key or a missing permission is logged naming the key type and the Speech to text permission', function (int $status, array $body, string $code) {
    Log::spy();
    Http::fake([VOICELAB_STT_URL => Http::response($body, $status)]);

    expect(fn () => aishaListener()->transcribe('clip-bytes', 'audio/wav', 'uyga', 'uz'))
        ->toThrow(SpeechToTextFailed::class, $code);
    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, $code)
        && str_contains($message, 'vlk_')
        && str_contains($message, 'Speech to text'))->once();
})->with([
    'an invalid key' => [401, ['message' => 'Invalid key.', 'error' => ['code' => 'invalid_api_key'], 'request_id' => 'req_01'], 'invalid_api_key'],
    'no permission' => [403, ['message' => 'Forbidden.', 'error' => ['code' => 'insufficient_scope'], 'request_id' => 'req_01'], 'insufficient_scope'],
    'the old Aisha API refusing the key format' => [403, ['detail' => 'API kalit formatida xatolik mavjud.', 'error_key' => 'invalid_api_key_format'], 'invalid_api_key_format'],
]);

test('without a key it fails before calling VoiceLab', function () {
    Http::fake();

    expect(fn () => (new AishaSpeechToText(apiKey: '', baseUrl: 'https://api.voicelab.uz', timeoutSeconds: 20))->transcribe('clip-bytes', 'audio/wav', 'uyga', 'uz'))
        ->toThrow(SpeechToTextFailed::class, 'AISHA_API_KEY');
    Http::assertNothingSent();
});
