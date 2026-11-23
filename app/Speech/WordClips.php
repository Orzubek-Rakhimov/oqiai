<?php

namespace App\Speech;

use App\Enums\SpeechPause;
use App\Models\Word;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

use function Illuminate\Support\defer;

/**
 * A word's voice clip for Syllable help: its syllables read slowly with
 * long pauses, then the whole word, in one clip from one voice request.
 *
 * A clip is made the first time a Child needs it and kept for every Child
 * after that. It is named by the word's syllables, so the same word in any
 * Story shares it. An apostrophe is written as "_" to keep the names plain:
 * o'yinchoq gives audio/clips/o_-yin-choq.wav.
 *
 * A word is voiced at most once: a lock keeps two requests from voicing it
 * together, and an existing clip is never voiced again (only the owner's
 * `audio:generate {word}` remakes one). After a failure the word waits
 * before it is tried again: as long as the service's Retry-After asks, or
 * else a wait that doubles with each failure.
 */
final readonly class WordClips
{
    public const string DISK = 'public';

    /**
     * The first wait after a failure, in seconds; each further failure doubles it.
     */
    public const int FIRST_WAIT_SECONDS = 15;

    /**
     * The longest wait after failures, in seconds.
     */
    public const int LONGEST_WAIT_SECONDS = 600;

    /**
     * After this many seconds of voicing in one request, no further word is
     * started, to stay well inside PHP's time limit for the request.
     */
    private const int SECONDS_PER_REQUEST = 30;

    public function __construct(private TextToSpeech $voice) {}

    /**
     * Get the path of a word's clip on the public disk.
     *
     * @param  list<string>  $syllables
     */
    public static function path(array $syllables): string
    {
        return 'audio/clips/'.str_replace("'", '_', implode('-', $syllables)).'.wav';
    }

    /**
     * Get the URL of the word's clip, or null while it is not made yet.
     */
    public static function url(Word $word): ?string
    {
        $disk = Storage::disk(self::DISK);
        $path = self::path($word->syllables);

        return $disk->exists($path) ? $disk->url($path) : null;
    }

    /**
     * Make the missing clips of the words once the response is sent, so the
     * Child never waits for the voice. It stops at the first failure, and
     * starts no further word once it has voiced for a while.
     *
     * @param  iterable<Word>  $words
     */
    public function makeAfterResponse(iterable $words): void
    {
        $missing = collect($words)->filter(fn (Word $word): bool => self::url($word) === null)->values();

        if ($missing->isEmpty()) {
            return;
        }

        defer(function () use ($missing): void {
            $startedAt = microtime(true);

            foreach ($missing as $word) {
                if (microtime(true) - $startedAt > self::SECONDS_PER_REQUEST || ! $this->makeMissing($word)) {
                    return;
                }
            }
        });
    }

    /**
     * Make the word's clip unless it exists, another request is making it,
     * or the word is still waiting after a failure. Returns false only when
     * the voice service failed.
     */
    public function makeMissing(Word $word): bool
    {
        $path = self::path($word->syllables);

        if (self::url($word) !== null || $this->waitingUntil($path) > now()->getTimestamp()) {
            return true;
        }

        $lock = Cache::lock('word-clip|'.$path, (int) config('services.text_to_speech.timeout') + 30);

        if (! $lock->get()) {
            return true;
        }

        try {
            if (! Storage::disk(self::DISK)->exists($path)) {
                $this->make($word);
            }

            return true;
        } catch (TextToSpeechFailed $failure) {
            $this->wait($path, $failure);

            return false;
        } finally {
            $lock->release();
        }
    }

    /**
     * Voice the word and save its clip, replacing any clip it had.
     *
     * @return string The clip's path on the public disk.
     *
     * @throws TextToSpeechFailed When the voice service fails or times out.
     */
    public function make(Word $word): string
    {
        $path = self::path($word->syllables);

        Storage::disk(self::DISK)->put($path, $this->voice->speak(
            [...$word->syllables, $word->normalized],
            new SpeakingOptions(slowly: true, pauseBetweenParts: SpeechPause::Long),
        ));
        Cache::forget($this->waitKey($path));

        return $path;
    }

    /**
     * Note a failure: the word waits before it is tried again.
     */
    private function wait(string $path, TextToSpeechFailed $failure): void
    {
        $key = $this->waitKey($path);
        $failures = $this->failures($key) + 1;

        $seconds = $failure instanceof TextToSpeechRateLimited && $failure->retryAfterSeconds !== null
            ? $failure->retryAfterSeconds
            : min(self::FIRST_WAIT_SECONDS * 2 ** ($failures - 1), self::LONGEST_WAIT_SECONDS);

        Cache::put($key, ['failures' => $failures, 'until' => now()->getTimestamp() + $seconds], now()->addDay());
    }

    private function failures(string $key): int
    {
        $wait = Cache::get($key);

        return is_array($wait) && is_int($wait['failures'] ?? null) ? $wait['failures'] : 0;
    }

    /**
     * Get the time before which the word is not tried again.
     */
    private function waitingUntil(string $path): int
    {
        $wait = Cache::get($this->waitKey($path));

        return is_array($wait) && is_int($wait['until'] ?? null) ? $wait['until'] : 0;
    }

    private function waitKey(string $path): string
    {
        return 'word-clip-wait|'.$path;
    }
}
