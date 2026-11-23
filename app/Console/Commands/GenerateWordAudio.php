<?php

namespace App\Console\Commands;

use App\Models\Word;
use App\Speech\TextToSpeechFailed;
use App\Speech\TextToSpeechRateLimited;
use App\Speech\WordClips;
use App\Stories\UzbekWord;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * The voice clips for Syllable help, made ahead. This is optional: a
 * word's clip is made the first time a Child needs it (WordClips). Run
 * without a word, it makes the clip of every Story word that has none;
 * naming one word regenerates its clip, for a word that sounds wrong.
 * When the voice service is rate limited, the command waits as long as it
 * asks (or a minute) and tries the word again.
 */
#[Signature('audio:generate {word? : Regenerate the clip of this one word (capitals and apostrophe style do not matter)}')]
#[Description('Generate the missing voice clips of every Story word ahead of use, or regenerate the clip of one word')]
class GenerateWordAudio extends Command
{
    /**
     * How many times a rate-limited word is tried before it is reported as failed.
     */
    private const int TRIES = 5;

    /**
     * How long to wait after a rate limit that gives no Retry-After, in seconds.
     */
    private const int DEFAULT_WAIT_SECONDS = 60;

    public function handle(WordClips $clips): int
    {
        $named = $this->argument('word');
        $words = $this->words(is_string($named) ? UzbekWord::normalize($named) : null);

        if ($words === []) {
            $this->components->error("The word \"{$named}\" is in no Story, so no clips were made.");

            return self::FAILURE;
        }

        $disk = Storage::disk(WordClips::DISK);
        $created = 0;
        $skipped = 0;
        $succeeded = true;

        foreach ($words as $word) {
            $path = WordClips::path($word->syllables);

            if (! is_string($named) && $disk->exists($path)) {
                $skipped++;

                continue;
            }

            if (! $this->make($clips, $word)) {
                $succeeded = false;

                continue;
            }

            $created++;
            $this->components->twoColumnDetail(implode(' - ', $word->syllables).' · '.$word->normalized, $path);
        }

        $this->components->info("Created {$created} ".Str::plural('clip', $created).", skipped {$skipped} that already existed.");

        return $succeeded ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Make the word's clip, waiting and trying again while the voice service is rate limited.
     */
    private function make(WordClips $clips, Word $word): bool
    {
        $path = WordClips::path($word->syllables);

        for ($try = 1; ; $try++) {
            try {
                $clips->make($word);

                return true;
            } catch (TextToSpeechRateLimited $rateLimited) {
                if ($try >= self::TRIES) {
                    $this->components->error("{$path}: the voice service failed, still rate limited after {$try} tries.");

                    return false;
                }

                $seconds = $rateLimited->retryAfterSeconds ?? self::DEFAULT_WAIT_SECONDS;
                $this->components->warn("{$path}: the voice service is rate limited, waiting {$seconds} s.");
                Sleep::for($seconds)->seconds();
            } catch (TextToSpeechFailed $exception) {
                $this->components->error("{$path}: the voice service failed ({$exception->getMessage()}).");

                return false;
            }
        }
    }

    /**
     * Get each distinct Story word, or only the named one.
     *
     * @return list<Word>
     */
    private function words(?string $normalized): array
    {
        return array_values(Word::query()
            ->when($normalized !== null, fn (Builder $query) => $query->where('normalized', $normalized))
            ->orderBy('id')
            ->get(['normalized', 'syllables'])
            ->reject(fn (Word $word): bool => $word->normalized === '' || $word->syllables === [])
            ->unique(fn (Word $word): string => WordClips::path($word->syllables))
            ->all());
    }
}
