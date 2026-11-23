<?php

namespace App\Speech;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Calls to the VoiceLab (Aisha) developer API at api.voicelab.uz, which both
 * the listening and the voice drivers use. The key is a developer key in the
 * vlk_… format, sent as "Authorization: Bearer vlk_…", as VoiceLab's
 * developer documentation (docs.voicelab.uz/api) shows.
 *
 * Errors come in VoiceLab's envelope ({"message", "error": {"code"},
 * "request_id"}); the older Aisha API's {"detail", "error_key"} is read too.
 * A refused key (401) or a missing permission (403) is logged with the
 * permission the key needs. When the caller tells rate limits apart, a 429
 * and a 402 (credits used up) are reported as rate limits, with the
 * Retry-After the answer gave.
 */
final readonly class AishaApi
{
    public const string BASE_URL = 'https://api.voicelab.uz';

    /**
     * How long to wait between polls of a job when VoiceLab doesn't say (it asks for about once a second).
     */
    private const int POLL_SECONDS = 1;

    private string $baseUrl;

    /**
     * @param  string  $permission  The key permission the caller needs, as VoiceLab's dashboard names it, with its level (such as "Speech to text permission at level Access").
     * @param  class-string<RuntimeException>  $failure  The exception to throw when a call fails or its answer can't be read.
     * @param  class-string<TextToSpeechRateLimited>|null  $rateLimited  The exception to throw for a rate-limit or used-up credits answer, when the caller tells it apart.
     */
    public function __construct(
        private string $apiKey,
        string $baseUrl,
        private int $timeoutSeconds,
        private string $permission,
        private string $failure,
        private ?string $rateLimited = null,
    ) {
        $this->baseUrl = rtrim($baseUrl !== '' ? $baseUrl : self::BASE_URL, '/');
    }

    /**
     * Upload a multipart form, as a new request with its own idempotency key.
     *
     * @param  array<string, string>  $fields
     * @param  array{name: string, contents: string, filename: string}  $file
     * @param  list<string>  $answeredCodes  Error codes the caller handles itself: their answers are given back, not thrown.
     *
     * @throws RuntimeException Of the failure class, when the call fails or times out.
     */
    public function postMultipart(string $path, array $fields, array $file, array $answeredCodes = []): Response
    {
        $request = $this->request()
            ->acceptJson()
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->asMultipart()
            ->attach($file['name'], $file['contents'], $file['filename']);

        return $this->send(fn (): Response => $request->post($this->url($path), $fields), $answeredCodes);
    }

    /**
     * Post a JSON body, as a new request with its own idempotency key.
     *
     * @param  array<string, mixed>  $body
     *
     * @throws RuntimeException Of the failure class, when the call fails or times out.
     */
    public function postJson(string $path, array $body, string $accept): Response
    {
        $request = $this->request()
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->accept($accept)
            ->asJson();

        return $this->send(fn (): Response => $request->post($this->url($path), $body));
    }

    /**
     * Get a JSON object from the API.
     *
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     *
     * @throws RuntimeException Of the failure class, when the call fails or gives no JSON object.
     */
    public function getJson(string $path, array $query = []): array
    {
        return $this->object($this->send(fn (): Response => $this->request()->acceptJson()->get($this->url($path), $query)));
    }

    /**
     * Poll a background job until it is completed or failed, waiting as VoiceLab asks, within the timeout.
     *
     * @return array<string, mixed> The job's last answer, with status "completed" or "failed".
     *
     * @throws RuntimeException Of the failure class, when a poll fails or the job is still pending when the time is up.
     */
    public function poll(string $path): array
    {
        $deadline = Carbon::now()->addSeconds($this->timeoutSeconds);

        while (true) {
            $response = $this->send(fn (): Response => $this->request()->acceptJson()->get($this->url($path)));
            $job = $this->object($response);
            $status = $job['status'] ?? null;

            if ($status === 'completed' || $status === 'failed') {
                return $job;
            }

            $seconds = max(1, $this->retryAfterSeconds($response) ?? self::POLL_SECONDS);

            if (Carbon::now()->addSeconds($seconds)->greaterThan($deadline)) {
                $this->fail('The VoiceLab job '.$path.' is still '.(is_string($status) ? $status : 'pending').' after '.$this->timeoutSeconds.' seconds.');
            }

            Sleep::for($seconds)->seconds();
        }
    }

    /**
     * Download a file by the signed URL VoiceLab gave, which must be fetched without the key.
     *
     * @throws RuntimeException Of the failure class, when the download fails or times out.
     */
    public function download(string $url): string
    {
        try {
            $response = Http::connectTimeout(5)->timeout($this->timeoutSeconds)->get($url);
        } catch (ConnectionException $exception) {
            $this->fail('The VoiceLab audio could not be downloaded: '.$exception->getMessage());
        }

        if ($response->failed()) {
            $this->fail('The VoiceLab audio download answered '.$response->status().'.');
        }

        return $response->body();
    }

    /**
     * Get the JSON object of an answer.
     *
     * @return array<string, mixed>
     */
    public function object(Response $response): array
    {
        $answer = $response->json();

        if (! is_array($answer) || array_is_list($answer)) {
            $this->fail('The VoiceLab API gave an answer that is not a JSON object.');
        }

        return $answer;
    }

    /**
     * Get the machine-readable error code of an answer or of a failed job, if it has one.
     */
    public function errorCode(mixed $answer): ?string
    {
        if ($answer instanceof Response) {
            $answer = $answer->json();
        }

        if (! is_array($answer)) {
            return null;
        }

        $code = data_get($answer, 'error.code') ?? $answer['error_key'] ?? null;

        if ($code === null && is_string($answer['error'] ?? null)) {
            $code = $answer['error'];
        }

        return is_string($code) ? $code : null;
    }

    /**
     * Throw the failure class, for answers the caller reads itself.
     */
    public function fail(string $message): never
    {
        throw new ($this->failure)($message);
    }

    private function url(string $path): string
    {
        return Str::startsWith($path, ['https://', 'http://']) ? $path : $this->baseUrl.'/'.ltrim($path, '/');
    }

    private function request(): PendingRequest
    {
        if ($this->apiKey === '') {
            $this->fail('AISHA_API_KEY is not set.');
        }

        return Http::withToken($this->apiKey)
            ->connectTimeout(5)
            ->timeout($this->timeoutSeconds);
    }

    /**
     * Make a call, turning a connection failure, a timeout or a failed answer into the failure class.
     *
     * @param  callable(): Response  $call
     * @param  list<string>  $answeredCodes
     */
    private function send(callable $call, array $answeredCodes = []): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException $exception) {
            $this->fail('The VoiceLab API could not be reached: '.$exception->getMessage());
        }

        if (! $response->failed()) {
            return $response;
        }

        $code = $this->errorCode($response);

        if ($code !== null && in_array($code, $answeredCodes, true)) {
            return $response;
        }

        $summary = $response->status().($code !== null ? ' '.$code : '').': '.$this->excerpt($response);

        if (in_array($response->status(), [401, 403], true)) {
            $message = 'The VoiceLab API refused AISHA_API_KEY ('.$summary.'). It must be a VoiceLab developer key (vlk_…) with the '
                .$this->permission.' (voicelab.uz → developer → API keys).';
            Log::error($message);
            $this->fail($message);
        }

        if ($this->rateLimited !== null && in_array($response->status(), [402, 429], true)) {
            throw new ($this->rateLimited)('The VoiceLab API refused for now ('.$summary.')', $this->retryAfterSeconds($response));
        }

        $this->fail('The VoiceLab API answered '.$summary);
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
     * The user-safe message of an error answer, with its request ID, for the log.
     */
    private function excerpt(Response $response): string
    {
        $answer = $response->json();
        $message = is_array($answer) ? ($answer['message'] ?? $answer['detail'] ?? null) : null;
        $requestId = is_array($answer) && is_string($answer['request_id'] ?? null) ? ' (request '.$answer['request_id'].')' : '';

        return Str::limit(is_string($message) ? $message : trim($response->body()), 200).$requestId;
    }
}
