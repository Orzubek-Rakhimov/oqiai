<?php

namespace App\Speech;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Calls to the Aisha (VoiceLab) API at back.aisha.group, which both the
 * listening and the voice drivers use. The key goes in the X-Api-Key header
 * and the fields go as a multipart form, as Aisha's documentation shows.
 *
 * Aisha documents no rate limit, no 429 and no shape for its error bodies,
 * only the statuses 400, 401, 402 (balance used up), 403 and 503. When the
 * caller tells rate limits apart, a 429 (should one ever come, with its
 * Retry-After) and a 402 are reported as rate limits, so the clip is tried
 * again after a wait instead of at once.
 */
final readonly class AishaApi
{
    public const string BASE_URL = 'https://back.aisha.group';

    /**
     * @param  class-string<RuntimeException>  $failure  The exception to throw when a call fails or its answer can't be read.
     * @param  class-string<TextToSpeechRateLimited>|null  $rateLimited  The exception to throw for a rate-limit or used-up balance answer, when the caller tells it apart.
     */
    public function __construct(
        private string $apiKey,
        private int $timeoutSeconds,
        private string $failure,
        private ?string $rateLimited = null,
    ) {}

    /**
     * Post a multipart form and get the JSON object Aisha answers with.
     *
     * @param  array<string, string>  $fields
     * @param  array{name: string, contents: string, filename: string}|null  $file
     * @return array<string, mixed>
     *
     * @throws RuntimeException Of the failure class, when the call fails, times out or gives no JSON object.
     */
    public function post(string $path, array $fields, ?array $file = null): array
    {
        $request = $this->request()->asMultipart();

        if ($file !== null) {
            $request = $request->attach($file['name'], $file['contents'], $file['filename']);
        }

        $response = $this->send(fn (): Response => $request->post(self::BASE_URL.$path, $fields));
        $answer = $response->json();

        if (! is_array($answer) || array_is_list($answer)) {
            $this->fail('The Aisha API gave an answer that is not a JSON object.');
        }

        return $answer;
    }

    /**
     * Download a file Aisha made, by the path it gave (such as "/media/tts_audios/x.wav").
     *
     * @throws RuntimeException Of the failure class, when the download fails or times out.
     */
    public function download(string $path): string
    {
        $url = Str::startsWith($path, ['https://', 'http://']) ? $path : self::BASE_URL.'/'.ltrim($path, '/');

        return $this->send(fn (): Response => $this->request()->get($url))->body();
    }

    private function request(): PendingRequest
    {
        if ($this->apiKey === '') {
            $this->fail('AISHA_API_KEY is not set.');
        }

        return Http::withHeaders(['X-Api-Key' => $this->apiKey])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout($this->timeoutSeconds);
    }

    /**
     * Make a call, turning a connection failure, a timeout or a failed answer into the failure class.
     *
     * @param  callable(): Response  $call
     */
    private function send(callable $call): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException $exception) {
            $this->fail('The Aisha API could not be reached: '.$exception->getMessage());
        }

        if ($response->failed() && $this->rateLimited !== null && in_array($response->status(), [402, 429], true)) {
            throw new ($this->rateLimited)('The Aisha API refused for now ('.$response->status().'): '.$this->excerpt($response), $this->retryAfterSeconds($response));
        }

        if ($response->failed()) {
            $this->fail('The Aisha API answered '.$response->status().': '.$this->excerpt($response));
        }

        return $response;
    }

    /**
     * Get how long the answer asks to wait, from its Retry-After header (in seconds or as a date).
     */
    private function retryAfterSeconds(Response $response): ?int
    {
        $header = trim($response->header('Retry-After'));

        if (ctype_digit($header)) {
            return (int) $header;
        }

        if ($header !== '' && ($date = strtotime($header)) !== false) {
            return max(0, $date - now()->getTimestamp());
        }

        return null;
    }

    /**
     * The start of an error answer, for the log: Aisha documents no shape for it.
     */
    private function excerpt(Response $response): string
    {
        return Str::limit(trim($response->body()), 200);
    }

    private function fail(string $message): never
    {
        throw new ($this->failure)($message);
    }
}
