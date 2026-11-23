<?php

namespace App\Speech;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use stdClass;

/**
 * Listens with Google Cloud Speech-to-Text V2 and its chirp_3 model, the
 * fallback to the Gemini listener, with word time offsets.
 *
 * There are no phrase hints (adaptation) and the expected text is never
 * sent, so a misread word is never nudged toward the right one.
 *
 * Speech-to-Text takes no API key for this call, only an OAuth access token.
 * The driver gets one as a service account: it signs a JSON web token with
 * the account's key file (RS256, PHP's openssl) and trades it at Google's
 * token address. The token is kept in the cache until five minutes before
 * it expires.
 */
final readonly class ChirpSpeechToText implements SpeechToText
{
    private const string SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    private const string TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * How long before the access token expires it is replaced, in seconds.
     */
    private const int TOKEN_MARGIN = 300;

    /**
     * @param  string  $keyFile  The path of the service account's JSON key file.
     * @param  string|null  $project  The Google Cloud project; null takes the key file's.
     */
    public function __construct(
        private string $keyFile,
        private string $model,
        private string $location,
        private ?string $project,
        private int $timeoutSeconds,
    ) {}

    public function transcribe(string $audio, string $mimeType, string $expectedText, string $language): array
    {
        $account = $this->serviceAccount();
        $project = $this->project ?: $account['project_id'];

        $response = $this->send(fn () => Http::withToken($this->accessToken($account))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout($this->timeoutSeconds)
            ->post($this->recognizeUrl($project), [
                'config' => [
                    'autoDecodingConfig' => new stdClass,
                    'model' => $this->model,
                    'languageCodes' => [GoogleSpeech::languageCode($language)],
                    'features' => ['enableWordTimeOffsets' => true],
                ],
                'content' => base64_encode($audio),
            ]));

        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey($account));
        }

        if ($response->failed()) {
            throw new SpeechToTextFailed('Speech-to-Text answered '.$response->status().': '.$response->json('error.message', ''));
        }

        $answer = $response->json();

        if (! is_array($answer)) {
            throw new SpeechToTextFailed('Speech-to-Text gave an answer that is not JSON.');
        }

        return $this->wordsOf(is_array($answer['results'] ?? null) ? $answer['results'] : []);
    }

    /**
     * Get the words of the results, in order: from the word times when there are any, or else from the transcripts.
     *
     * @param  array<mixed>  $results
     * @return list<RecognisedWord>
     */
    private function wordsOf(array $results): array
    {
        $words = [];
        $transcript = '';

        foreach ($results as $result) {
            $best = is_array($result) && is_array($result['alternatives'][0] ?? null) ? $result['alternatives'][0] : [];
            $transcript .= ' '.(is_string($best['transcript'] ?? null) ? $best['transcript'] : '');

            foreach (is_array($best['words'] ?? null) ? $best['words'] : [] as $word) {
                if (is_array($word) && is_string($word['word'] ?? null) && trim($word['word']) !== '') {
                    $words[] = new RecognisedWord(
                        trim($word['word']),
                        startSeconds: GoogleSpeech::seconds($word['startOffset'] ?? null),
                        endSeconds: GoogleSpeech::seconds($word['endOffset'] ?? null),
                        confidence: is_numeric($word['confidence'] ?? null) ? (float) $word['confidence'] : null,
                    );
                }
            }
        }

        return $words !== [] ? $words : RecognisedWord::inTranscript($transcript);
    }

    /**
     * The regional address of the recognize call, on the project's default recognizer ("_").
     */
    private function recognizeUrl(string $project): string
    {
        $host = $this->location === 'global' ? 'speech.googleapis.com' : $this->location.'-speech.googleapis.com';

        return "https://{$host}/v2/projects/{$project}/locations/{$this->location}/recognizers/_:recognize";
    }

    /**
     * Read the service account's key file.
     *
     * @return array{project_id: string, client_email: string, private_key: string, private_key_id: string|null, token_uri: string}
     */
    private function serviceAccount(): array
    {
        $contents = $this->keyFile !== '' && is_file($this->keyFile) ? file_get_contents($this->keyFile) : false;
        $account = is_string($contents) ? json_decode($contents, true) : null;

        if (! is_array($account) || ! is_string($account['client_email'] ?? null) || ! is_string($account['private_key'] ?? null) || ! is_string($account['project_id'] ?? null)) {
            throw new SpeechToTextFailed('The Google service account key file (GOOGLE_APPLICATION_CREDENTIALS) is missing or unreadable.');
        }

        return [
            'project_id' => $account['project_id'],
            'client_email' => $account['client_email'],
            'private_key' => $account['private_key'],
            'private_key_id' => is_string($account['private_key_id'] ?? null) ? $account['private_key_id'] : null,
            'token_uri' => is_string($account['token_uri'] ?? null) ? $account['token_uri'] : self::TOKEN_URL,
        ];
    }

    /**
     * Get an access token for the service account, from the cache or a new sign-in.
     *
     * @param  array{project_id: string, client_email: string, private_key: string, private_key_id: string|null, token_uri: string}  $account
     */
    private function accessToken(array $account): string
    {
        $cacheKey = $this->tokenCacheKey($account);
        $token = Cache::get($cacheKey);

        if (is_string($token)) {
            return $token;
        }

        $assertion = $this->signedAssertion($account);

        $response = $this->send(fn () => Http::asForm()
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->post($account['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]));

        $token = $response->json('access_token');
        $expiresIn = $response->json('expires_in');

        if ($response->failed() || ! is_string($token) || $token === '' || ! is_numeric($expiresIn)) {
            throw new SpeechToTextFailed('Google refused the service account sign-in ('.$response->status().').');
        }

        Cache::put($cacheKey, $token, max(60, (int) $expiresIn - self::TOKEN_MARGIN));

        return $token;
    }

    /**
     * Make the JSON web token that asks Google for an access token, signed with the account's key.
     *
     * @param  array{project_id: string, client_email: string, private_key: string, private_key_id: string|null, token_uri: string}  $account
     */
    private function signedAssertion(array $account): string
    {
        $now = now()->getTimestamp();
        $header = array_filter(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $account['private_key_id']]);
        $claims = ['iss' => $account['client_email'], 'scope' => self::SCOPE, 'aud' => $account['token_uri'], 'iat' => $now, 'exp' => $now + 3600];

        $unsigned = $this->base64Url((string) json_encode($header)).'.'.$this->base64Url((string) json_encode($claims));
        $key = openssl_pkey_get_private($account['private_key']);

        if ($key === false || ! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new SpeechToTextFailed('The Google service account key could not sign the sign-in.');
        }

        return $unsigned.'.'.$this->base64Url($signature);
    }

    /**
     * @param  array{project_id: string, client_email: string, private_key: string, private_key_id: string|null, token_uri: string}  $account
     */
    private function tokenCacheKey(array $account): string
    {
        return 'chirp-access-token:'.sha1($account['client_email']);
    }

    /**
     * Make a call, turning a connection failure or a timeout into a speech-to-text failure.
     *
     * @param  callable(): Response  $call
     */
    private function send(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException $exception) {
            throw new SpeechToTextFailed('Google could not be reached: '.$exception->getMessage());
        }
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
