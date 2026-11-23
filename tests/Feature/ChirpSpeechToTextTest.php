<?php

use App\Speech\ChirpSpeechToText;
use App\Speech\RecognisedWord;
use App\Speech\SpeechToTextFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const CHIRP_RECOGNIZE_URL = 'https://us-speech.googleapis.com/v2/projects/oqi-test/locations/us/recognizers/_:recognize';
const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';

beforeEach(function () {
    Http::preventStrayRequests();

    // A throwaway key made for this test. PHP's openssl needs a config file
    // to make one, and an empty file is enough.
    $openSslConfig = tempnam(sys_get_temp_dir(), 'openssl');
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $openSslConfig]);
    openssl_pkey_export($key, $privateKey, null, ['config' => $openSslConfig]);
    unlink($openSslConfig);

    $this->publicKey = openssl_pkey_get_details($key)['key'];
    $this->keyFile = tempnam(sys_get_temp_dir(), 'service-account');
    file_put_contents($this->keyFile, json_encode([
        'type' => 'service_account',
        'project_id' => 'oqi-test',
        'private_key_id' => 'key-id-1',
        'private_key' => $privateKey,
        'client_email' => 'listener@oqi-test.iam.gserviceaccount.com',
        'token_uri' => GOOGLE_TOKEN_URL,
    ]));
});

afterEach(function () {
    @unlink($this->keyFile);
});

function chirpListener(string $keyFile, ?string $project = null): ChirpSpeechToText
{
    return new ChirpSpeechToText(keyFile: $keyFile, model: 'chirp_3', location: 'us', project: $project, timeoutSeconds: 20);
}

/**
 * @return array<string, mixed>
 */
function signedIn(): array
{
    return ['access_token' => 'test-access-token', 'expires_in' => 3599, 'token_type' => 'Bearer'];
}

/**
 * A recognition result as Speech-to-Text V2 gives it.
 *
 * @param  list<array<string, mixed>>  $words
 * @return array<string, mixed>
 */
function recognised(string $transcript, array $words = []): array
{
    return ['alternatives' => [['transcript' => $transcript, 'confidence' => 0.9, 'words' => $words]], 'languageCode' => 'uz-uz'];
}

/**
 * Decode one part of a JSON web token.
 *
 * @return array<string, mixed>
 */
function tokenPart(string $part): array
{
    return json_decode(base64_decode(strtr($part, '-_', '+/')), true);
}

test('it signs in as the service account and sends the clip to chirp_3 in Uzbek with word times and no phrase hints', function () {
    Http::fake([
        GOOGLE_TOKEN_URL => Http::response(signedIn()),
        CHIRP_RECOGNIZE_URL => Http::response(['results' => [recognised('Ali uyga')]]),
    ]);

    chirpListener($this->keyFile)->transcribe('clip-bytes', 'audio/webm;codecs=opus', 'Ali uyga keldi.', 'uz');

    Http::assertSentCount(2);
    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== GOOGLE_TOKEN_URL) {
            return false;
        }

        expect($request['grant_type'])->toBe('urn:ietf:params:oauth:grant-type:jwt-bearer');

        [$header, $claims, $signature] = explode('.', $request['assertion']);
        expect(tokenPart($header))->toBe(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'key-id-1'])
            ->and(tokenPart($claims))->toMatchArray([
                'iss' => 'listener@oqi-test.iam.gserviceaccount.com',
                'scope' => 'https://www.googleapis.com/auth/cloud-platform',
                'aud' => GOOGLE_TOKEN_URL,
            ])
            ->and(tokenPart($claims)['exp'] - tokenPart($claims)['iat'])->toBe(3600)
            ->and(openssl_verify("{$header}.{$claims}", base64_decode(strtr($signature, '-_', '+/')), $this->publicKey, OPENSSL_ALGO_SHA256))->toBe(1);

        return true;
    });
    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== CHIRP_RECOGNIZE_URL) {
            return false;
        }

        expect($request->method())->toBe('POST')
            ->and($request->header('Authorization'))->toBe(['Bearer test-access-token'])
            ->and($request->data())->toEqual([
                'config' => [
                    'autoDecodingConfig' => new stdClass,
                    'model' => 'chirp_3',
                    'languageCodes' => ['uz-UZ'],
                    'features' => ['enableWordTimeOffsets' => true],
                ],
                'content' => base64_encode('clip-bytes'),
            ])
            ->and($request->body())->toContain('"autoDecodingConfig":{}')
            ->and($request->body())->not->toContain('keldi')
            ->and($request->body())->not->toContain('adaptation');

        return true;
    });
});

test('it keeps the sign-in until shortly before it expires', function () {
    Http::fake([
        GOOGLE_TOKEN_URL => Http::response(signedIn()),
        CHIRP_RECOGNIZE_URL => Http::response(['results' => [recognised('Ali')]]),
    ]);

    chirpListener($this->keyFile)->transcribe('clip-bytes', 'audio/webm', 'Ali', 'uz');
    $this->travel(50)->minutes();
    chirpListener($this->keyFile)->transcribe('clip-bytes', 'audio/webm', 'Ali', 'uz');

    Http::assertSentCount(3);

    $this->travel(6)->minutes();
    chirpListener($this->keyFile)->transcribe('clip-bytes', 'audio/webm', 'Ali', 'uz');

    Http::assertSentCount(5);
});

test('the project can be set apart from the key file', function () {
    Http::fake([
        GOOGLE_TOKEN_URL => Http::response(signedIn()),
        'https://us-speech.googleapis.com/v2/projects/another-project/locations/us/recognizers/_:recognize' => Http::response(['results' => []]),
    ]);

    expect(chirpListener($this->keyFile, project: 'another-project')->transcribe('clip-bytes', 'audio/webm', 'Ali', 'uz'))->toBe([]);
});

test('it hears the words of every result in order, with their times and confidence', function () {
    Http::fake([
        GOOGLE_TOKEN_URL => Http::response(signedIn()),
        CHIRP_RECOGNIZE_URL => Http::response(['results' => [
            recognised('Ali uyga', [
                ['startOffset' => '0.100s', 'endOffset' => '0.400s', 'word' => 'Ali', 'confidence' => 0.92],
                ['startOffset' => '0.500s', 'endOffset' => '1.100s', 'word' => 'uyga'],
            ]),
            recognised('keldi', [
                ['startOffset' => '1.300s', 'endOffset' => '1.900s', 'word' => 'keldi', 'confidence' => 0.8],
            ]),
        ]]),
    ]);

    expect(chirpListener($this->keyFile)->transcribe('clip-bytes', 'audio/webm', 'Ali uyga keldi.', 'uz'))->toEqual([
        new RecognisedWord('Ali', startSeconds: 0.1, endSeconds: 0.4, confidence: 0.92),
        new RecognisedWord('uyga', startSeconds: 0.5, endSeconds: 1.1),
        new RecognisedWord('keldi', startSeconds: 1.3, endSeconds: 1.9, confidence: 0.8),
    ]);
});

test('without word times it hears the words of the transcripts in order', function () {
    Http::fake([
        GOOGLE_TOKEN_URL => Http::response(signedIn()),
        CHIRP_RECOGNIZE_URL => Http::response(['results' => [recognised('Ali uyga'), recognised(' keldi')]]),
    ]);

    expect(chirpListener($this->keyFile)->transcribe('clip-bytes', 'audio/webm', 'Ali uyga keldi.', 'uz'))->toEqual([
        new RecognisedWord('Ali'),
        new RecognisedWord('uyga'),
        new RecognisedWord('keldi'),
    ]);
});

test('no results means nothing was heard', function () {
    Http::fake([
        GOOGLE_TOKEN_URL => Http::response(signedIn()),
        CHIRP_RECOGNIZE_URL => Http::response([]),
    ]);

    expect(chirpListener($this->keyFile)->transcribe('clip-bytes', 'audio/webm', 'Ali', 'uz'))->toBe([]);
});

test('a refused sign-in is forgotten, so the next clip signs in again', function () {
    Http::fake([
        GOOGLE_TOKEN_URL => Http::response(signedIn()),
        CHIRP_RECOGNIZE_URL => Http::sequence()
            ->push(['error' => ['message' => 'Request had invalid authentication credentials.']], 401)
            ->push(['results' => [recognised('Ali')]]),
    ]);

    expect(fn () => chirpListener($this->keyFile)->transcribe('clip-bytes', 'audio/webm', 'Ali', 'uz'))->toThrow(SpeechToTextFailed::class);

    chirpListener($this->keyFile)->transcribe('clip-bytes', 'audio/webm', 'Ali', 'uz');

    Http::assertSentCount(4);
});

test('a failed sign-in, a service failure, a timeout or an answer it cannot read is a speech-to-text failure', function (Closure $tokenAnswer, Closure $recognizeAnswer) {
    Http::fake([GOOGLE_TOKEN_URL => $tokenAnswer, CHIRP_RECOGNIZE_URL => $recognizeAnswer]);

    chirpListener($this->keyFile)->transcribe('clip-bytes', 'audio/webm', 'Ali', 'uz');
})->with([
    'a refused sign-in' => [fn () => fn () => Http::response(['error' => 'invalid_grant'], 400), fn () => fn () => Http::response([])],
    'a sign-in without a token' => [fn () => fn () => Http::response(['token_type' => 'Bearer']), fn () => fn () => Http::response([])],
    'a sign-in timeout' => [fn () => fn () => throw new ConnectionException('timed out'), fn () => fn () => Http::response([])],
    'a server error' => [fn () => fn () => Http::response(signedIn()), fn () => fn () => Http::response(['error' => ['message' => 'Internal']], 500)],
    'a bad request' => [fn () => fn () => Http::response(signedIn()), fn () => fn () => Http::response(['error' => ['message' => 'Invalid recognizer']], 400)],
    'a timeout' => [fn () => fn () => Http::response(signedIn()), fn () => fn () => throw new ConnectionException('cURL error 28: Operation timed out')],
    'not JSON' => [fn () => fn () => Http::response(signedIn()), fn () => fn () => Http::response('<html>Bad gateway</html>', 200)],
])->throws(SpeechToTextFailed::class);

test('a missing or broken key file is a speech-to-text failure, and nothing is sent', function (string $problem) {
    Http::fake();

    $keyFile = match ($problem) {
        'no path set' => '',
        'no file there' => $this->keyFile.'-missing',
        'not JSON' => tap($this->keyFile, fn (string $path) => file_put_contents($path, 'not json')),
        'an unreadable key' => tap($this->keyFile, fn (string $path) => file_put_contents($path, json_encode([
            'project_id' => 'oqi-test',
            'client_email' => 'listener@oqi-test.iam.gserviceaccount.com',
            'private_key' => 'nonsense',
        ]))),
    };

    expect(fn () => chirpListener($keyFile)->transcribe('clip-bytes', 'audio/webm', 'Ali', 'uz'))
        ->toThrow(SpeechToTextFailed::class);

    Http::assertNothingSent();
})->with(['no path set', 'no file there', 'not JSON', 'an unreadable key']);
