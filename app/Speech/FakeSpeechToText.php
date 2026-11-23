<?php

namespace App\Speech;

/**
 * A speech-to-text driver that returns scripted words, for tests and local
 * demos. Each call takes the next scripted reply; with none left, it hears
 * the expected text read perfectly.
 */
final class FakeSpeechToText implements SpeechToText
{
    /**
     * The scripted replies: a transcript, or null for a service failure.
     *
     * @var list<string|null>
     */
    private array $replies = [];

    /**
     * Every clip the fake was asked to transcribe.
     *
     * @var list<array{audio: string, mimeType: string, expectedText: string, language: string}>
     */
    private array $requests = [];

    /**
     * Script the next replies, one transcript per call. An empty string means nothing was heard.
     */
    public function hears(string ...$transcripts): self
    {
        array_push($this->replies, ...$transcripts);

        return $this;
    }

    /**
     * Script the next reply as hearing nothing.
     */
    public function hearsNothing(): self
    {
        return $this->hears('');
    }

    /**
     * Script the next reply as a service failure.
     */
    public function fails(): self
    {
        $this->replies[] = null;

        return $this;
    }

    /**
     * Get every clip the fake was asked to transcribe, oldest first.
     *
     * @return list<array{audio: string, mimeType: string, expectedText: string, language: string}>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    public function transcribe(string $audio, string $mimeType, string $expectedText, string $language): array
    {
        $this->requests[] = compact('audio', 'mimeType', 'expectedText', 'language');

        $transcript = $this->replies === [] ? $expectedText : array_shift($this->replies);

        if ($transcript === null) {
            throw new SpeechToTextFailed('The fake speech-to-text service was scripted to fail.');
        }

        $words = preg_split('/\s+/u', trim($transcript), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(fn (string $word): RecognisedWord => new RecognisedWord($word), $words);
    }
}
