<?php

namespace App\Speech;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * One call to the Gemini API's Interactions endpoint, which both the
 * listening and the voice models answer. The key goes in the
 * x-goog-api-key header. Only the paid tier may be used: the free tier may
 * use what it is sent to improve Google's products (docs/deployment.md).
 */
final readonly class GeminiInteractions
{
    public const string URL = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    /**
     * @param  class-string<RuntimeException>  $failure  The exception to throw when the call fails or its answer can't be read.
     * @param  class-string<TextToSpeechRateLimited>|null  $rateLimited  The exception to throw for a rate-limit or quota answer, when the caller tells it apart.
     */
    public function __construct(
        private string $apiKey,
        private int $timeoutSeconds,
        private string $failure,
        private ?string $rateLimited = null,
    ) {}

    /**
     * Send the interaction and get the content of the model's output, in order.
     *
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException Of the failure class, when the call fails, times out or gives an unreadable or unfinished answer.
     */
    public function outputs(array $body): array
    {
        if ($this->apiKey === '') {
            $this->fail('GEMINI_API_KEY is not set.');
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout($this->timeoutSeconds)
                ->post(self::URL, $body);
        } catch (ConnectionException $exception) {
            $this->fail('The Gemini API could not be reached: '.$exception->getMessage());
        }

        if ($response->failed() && $this->rateLimited !== null && $this->isRateLimit($response)) {
            throw new ($this->rateLimited)(
                'The Gemini API is rate limited ('.$response->status().'): '.$response->json('error.message', ''),
                $this->retryAfterSeconds($response),
            );
        }

        if ($response->failed()) {
            $this->fail('The Gemini API answered '.$response->status().': '.$response->json('error.message', ''));
        }

        $interaction = $response->json();

        if (! is_array($interaction) || ($interaction['status'] ?? null) !== 'completed' || ! is_array($interaction['steps'] ?? null)) {
            $this->fail('The Gemini API gave no completed interaction.');
        }

        $outputs = [];

        foreach ($interaction['steps'] as $step) {
            if (is_array($step) && ($step['type'] ?? null) === 'model_output' && is_array($step['content'] ?? null)) {
                foreach ($step['content'] as $content) {
                    if (is_array($content)) {
                        $outputs[] = $content;
                    }
                }
            }
        }

        return $outputs;
    }

    /**
     * Determine whether the answer refuses for now: too many requests, or the quota used up.
     */
    private function isRateLimit(Response $response): bool
    {
        return $response->status() === 429 || $response->json('error.status') === 'RESOURCE_EXHAUSTED';
    }

    /**
     * Get how long the answer asks to wait, from its Retry-After header (in
     * seconds or as a date) or the RetryInfo in its error details.
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

        foreach ((array) $response->json('error.details', []) as $detail) {
            $delay = is_array($detail) ? ($detail['retryDelay'] ?? null) : null;

            if (is_string($delay) && preg_match('/^(\d+(?:\.\d+)?)s$/', $delay, $matches) === 1) {
                return (int) ceil((float) $matches[1]);
            }
        }

        return null;
    }

    private function fail(string $message): never
    {
        throw new ($this->failure)($message);
    }
}
