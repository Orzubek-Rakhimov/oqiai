<?php

use App\Speech\AishaSpeechToText;
use App\Speech\RecognisedWord;
use App\Speech\SpeechToTextFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const AISHA_STT_URL = 'https://back.aisha.group/api/v1/stt/post/';

beforeEach(function () {
    Http::preventStrayRequests();
});

function aishaListener(): AishaSpeechToText
{
    return new AishaSpeechToText(apiKey: 'test-aisha-key', timeoutSeconds: 20);
}

/**
 * A finished transcription, as Aisha's sync endpoint answers it.
 *
 * @return array<string, mixed>
 */
function aishaTranscript(string $transcript): array
{
    return ['id' => 531, 'gender' => 'unknown', 'title' => null, 'created_at' => '2026-05-04T10:12:43.212Z', 'duration' => 3.2, 'transcript' => $transcript];
}

test('it uploads the clip as a WAV file for an Uzbek transcription, with its key and nothing of the Story', function () {
    Http::fake([AISHA_STT_URL => Http::response(aishaTranscript('Ali uyga'))]);

    aishaListener()->transcribe('RIFF-clip-bytes', 'audio/x-wav', 'Ali uyga keldi.', 'uz');

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        $parts = collect($request->data())->keyBy('name');

        expect($request->method())->toBe('POST')
            ->and($request->url())->toBe(AISHA_STT_URL)
            ->and($request->header('X-Api-Key'))->toBe(['test-aisha-key'])
            ->and($request->isMultipart())->toBeTrue()
            ->and($parts->keys()->sort()->values()->all())->toBe(['audio', 'has_diarization', 'language'])
            ->and($parts['audio']['contents'])->toBe('RIFF-clip-bytes')
            ->and($parts['audio']['filename'])->toBe('clip.wav')
            ->and($parts['language']['contents'])->toBe('uz')
            ->and($parts['has_diarization']['contents'])->toBe('false')
            ->and($request->body())->not->toContain('keldi');

        return true;
    });
});

test('the clip gets the file name of its format, which Aisha checks', function (string $mimeType, string $filename) {
    Http::fake([AISHA_STT_URL => Http::response(aishaTranscript('uyga'))]);

    aishaListener()->transcribe('clip-bytes', $mimeType, 'uyga', 'uz');

    Http::assertSent(fn (Request $request): bool => collect($request->data())->firstWhere('name', 'audio')['filename'] === $filename);
})->with([
    'WAV' => ['audio/wav', 'clip.wav'],
    'WAV, as the content is detected' => ['audio/x-wav', 'clip.wav'],
    'an iPhone recording' => ['audio/mp4', 'clip.m4a'],
    'an iPhone recording, as the content is detected' => ['video/mp4', 'clip.m4a'],
    'Ogg' => ['audio/ogg; codecs=opus', 'clip.ogg'],
    'MP3' => ['audio/mpeg', 'clip.mp3'],
]);

test('it gives back the words of the transcript, in order', function () {
    Http::fake([AISHA_STT_URL => Http::response(aishaTranscript(" Ali  uyga\nkeldi. "))]);

    expect(aishaListener()->transcribe('clip-bytes', 'audio/wav', 'Ali uyga keldi.', 'uz'))->toEqual([
        new RecognisedWord('Ali'),
        new RecognisedWord('uyga'),
        new RecognisedWord('keldi.'),
    ]);
});

test('an empty transcript is nothing heard', function (mixed $transcript) {
    Http::fake([AISHA_STT_URL => Http::response([...aishaTranscript(''), 'transcript' => $transcript])]);

    expect(aishaListener()->transcribe('clip-bytes', 'audio/wav', 'uyga', 'uz'))->toBe([]);
})->with(['empty' => [''], 'only spaces' => ['  '], 'null' => [null]]);

test('a service failure, a timeout or an unreadable answer is a speech-to-text failure', function (Closure $answer) {
    Http::fake([AISHA_STT_URL => $answer]);

    aishaListener()->transcribe('clip-bytes', 'audio/wav', 'uyga', 'uz');
})->with([
    'a refused format' => fn () => fn () => Http::response(['detail' => 'Invalid format'], 400),
    'a refused key' => fn () => fn () => Http::response(['detail' => 'Invalid API key'], 401),
    'the balance used up' => fn () => fn () => Http::response(['detail' => 'Insufficient balance'], 402),
    'a duration limit' => fn () => fn () => Http::response(['detail' => 'Duration limit'], 403),
    'too many requests' => fn () => fn () => Http::response(['detail' => 'Throttled'], 429),
    'the service unavailable' => fn () => fn () => Http::response(['detail' => 'Unavailable'], 503),
    'a timeout' => fn () => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
    'not JSON' => fn () => fn () => Http::response('<html>Bad gateway</html>', 200),
    'no transcript' => fn () => fn () => Http::response(['id' => 531]),
])->throws(SpeechToTextFailed::class);

test('without a key it fails before calling Aisha', function () {
    Http::fake();

    expect(fn () => (new AishaSpeechToText(apiKey: '', timeoutSeconds: 20))->transcribe('clip-bytes', 'audio/wav', 'uyga', 'uz'))
        ->toThrow(SpeechToTextFailed::class, 'AISHA_API_KEY');
    Http::assertNothingSent();
});
