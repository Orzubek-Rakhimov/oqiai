<?php

namespace App\Speech;

/**
 * A text-to-speech driver that needs no service, for tests and local demos.
 * Every clip is a short, silent WAV file; a scripted failure or rate limit
 * makes the next call fail.
 */
final class FakeTextToSpeech implements TextToSpeech
{
    /**
     * The sample rate of the silent clips, the same as the real voice's.
     */
    private const int SAMPLE_RATE = 24000;

    /**
     * How the next calls fail, oldest first.
     *
     * @var list<TextToSpeechFailed>
     */
    private array $failures = [];

    /**
     * Every text the fake was asked to read.
     *
     * @var list<array{parts: list<string>, slowly: bool, pause: string, language: string}>
     */
    private array $requests = [];

    /**
     * Script the next call as a service failure.
     */
    public function fails(): self
    {
        $this->failures[] = new TextToSpeechFailed('The fake text-to-speech service was scripted to fail.');

        return $this;
    }

    /**
     * Script the next call as a rate-limit answer, with the Retry-After it gives.
     */
    public function rateLimited(?int $retryAfterSeconds = null): self
    {
        $this->failures[] = new TextToSpeechRateLimited('The fake text-to-speech service was scripted to be rate limited.', $retryAfterSeconds);

        return $this;
    }

    /**
     * Get every text the fake was asked to read, oldest first.
     *
     * @return list<array{parts: list<string>, slowly: bool, pause: string, language: string}>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    public function speak(array $parts, SpeakingOptions $options): string
    {
        $this->requests[] = [
            'parts' => $parts,
            'slowly' => $options->slowly,
            'pause' => $options->pauseBetweenParts->value,
            'language' => $options->language,
        ];

        $failure = array_shift($this->failures);

        if ($failure !== null) {
            throw $failure;
        }

        return $this->silence(seconds: 0.25 * count($parts));
    }

    /**
     * Make a WAV file of silence: 16-bit mono PCM.
     */
    private function silence(float $seconds): string
    {
        $samples = str_repeat("\0\0", (int) (self::SAMPLE_RATE * $seconds));

        return 'RIFF'.pack('V', 36 + strlen($samples)).'WAVE'
            .'fmt '.pack('VvvVVvv', 16, 1, 1, self::SAMPLE_RATE, self::SAMPLE_RATE * 2, 2, 16)
            .'data'.pack('V', strlen($samples)).$samples;
    }
}
