<?php

namespace App\Console\Commands;

use App\Enums\SpeechPause;
use App\Models\Word;
use App\Speech\SpeakingOptions;
use App\Speech\TextToSpeech;
use App\Speech\TextToSpeechFailed;
use App\Speech\WordClips;
use App\Stories\UzbekWord;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The voice clips for Syllable help: for every Story word, its syllables
 * read slowly with long pauses, and the whole word read slowly. Clips that
 * exist are kept, so the command is safe to re-run after loading Stories;
 * naming one word regenerates its clips, for a word that sounds wrong.
 */
#[Signature('audio:generate {word? : Regenerate the clips of this one word (capitals and apostrophe style do not matter)}')]
#[Description('Generate the missing voice clips of every Story word, or regenerate the clips of one word')]
class GenerateWordAudio extends Command
{
    public function handle(TextToSpeech $voice): int
    {
        $named = $this->argument('word');
        $words = $this->words(is_string($named) ? UzbekWord::normalize($named) : null);

        if ($words === []) {
            $this->components->error("The word \"{$named}\" is in no Story, so no clips were made.");

            return self::FAILURE;
        }

        $disk = Storage::disk(WordClips::DISK);
        $options = new SpeakingOptions(slowly: true, pauseBetweenParts: SpeechPause::Long);
        $created = 0;
        $skipped = 0;
        $succeeded = true;

        foreach ($this->clips($words) as $path => $parts) {
            if (! is_string($named) && $disk->exists($path)) {
                $skipped++;

                continue;
            }

            try {
                $disk->put($path, $voice->speak($parts, $options));
            } catch (TextToSpeechFailed $exception) {
                $this->components->error("{$path}: the voice service failed ({$exception->getMessage()}).");
                $succeeded = false;

                continue;
            }

            $created++;
            $this->components->twoColumnDetail(implode(' - ', $parts), $path);
        }

        $this->components->info("Created {$created} ".Str::plural('clip', $created).", skipped {$skipped} that already existed.");

        return $succeeded ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Get each distinct Story word, or only the named one.
     *
     * @return list<array{normalized: string, syllables: list<string>}>
     */
    private function words(?string $normalized): array
    {
        return array_values(Word::query()
            ->when($normalized !== null, fn ($query) => $query->where('normalized', $normalized))
            ->orderBy('id')
            ->get(['normalized', 'syllables'])
            ->map(fn (Word $word): array => ['normalized' => $word->normalized, 'syllables' => $word->syllables])
            ->unique(fn (array $word): string => $word['normalized'].'|'.implode('-', $word['syllables']))
            ->all());
    }

    /**
     * Get the text of every clip to make, keyed by its path: the syllables, then the whole word.
     *
     * @param  list<array{normalized: string, syllables: list<string>}>  $words
     * @return array<string, non-empty-list<string>>
     */
    private function clips(array $words): array
    {
        $clips = [];

        foreach ($words as $word) {
            if ($word['normalized'] === '' || $word['syllables'] === []) {
                continue;
            }

            $paths = WordClips::paths($word['normalized'], $word['syllables']);
            $clips[$paths['syllables']] = $word['syllables'];
            $clips[$paths['word']] = [$word['normalized']];
        }

        return $clips;
    }
}
