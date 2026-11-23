<?php

namespace App\Console\Commands;

use App\Models\Story;
use App\Models\Word;
use App\Stories\InvalidStoryFile;
use App\Stories\StoryFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('stories:load {--path= : The folder of Story files (default: resources/stories)}')]
#[Description('Load every Story file into the app, creating new Stories and updating changed ones')]
class LoadStories extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $folder = $this->option('path') ?: resource_path('stories');
        $paths = glob(rtrim($folder, '/\\').'/*.txt') ?: [];

        if ($paths === []) {
            $this->components->error("No Story files (*.txt) found in {$folder}.");

            return self::FAILURE;
        }

        $succeeded = true;

        foreach ($paths as $path) {
            $slug = pathinfo($path, PATHINFO_FILENAME);

            try {
                $outcome = $this->load(StoryFile::read($path));
            } catch (InvalidStoryFile $exception) {
                $this->components->error("{$slug}: {$exception->getMessage()}.");
                $succeeded = false;

                continue;
            }

            if ($outcome === 'refused') {
                $this->components->error("{$slug}: refused. Children have Readings of this Story, so its words cannot change. The Story was left as it was.");
                $succeeded = false;

                continue;
            }

            $this->components->twoColumnDetail($slug, $outcome);
        }

        return $succeeded ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Create or update the Story from its file.
     *
     * @return 'created'|'updated'|'unchanged'|'refused'
     */
    private function load(StoryFile $file): string
    {
        return DB::transaction(function () use ($file): string {
            $story = Story::with('sentences.words')->firstWhere('slug', $file->slug);

            if ($story === null) {
                $story = Story::create($this->headerOf($file));
                $this->createSentences($story, $file);

                return 'created';
            }

            $wordsChange = $this->wordTextsOf($story) !== $this->wordTextsOfFile($file);

            if ($wordsChange && $story->readings()->exists()) {
                return 'refused';
            }

            $story->fill($this->headerOf($file));
            $changed = $story->isDirty();
            $story->save();

            if ($wordsChange) {
                $story->sentences()->delete();
                $this->createSentences($story, $file);

                return 'updated';
            }

            foreach ($file->sentences as $sentencePosition => $sentence) {
                foreach ($sentence['words'] as $wordPosition => $word) {
                    $storedWord = $story->sentences[$sentencePosition]->words[$wordPosition];
                    $storedWord->syllables = $word['syllables'];
                    $changed = $storedWord->isDirty() || $changed;
                    $storedWord->save();
                }
            }

            return $changed ? 'updated' : 'unchanged';
        });
    }

    /**
     * @return array{slug: string, title: string, level: int, minutes: int}
     */
    private function headerOf(StoryFile $file): array
    {
        return [
            'slug' => $file->slug,
            'title' => $file->title,
            'level' => $file->level,
            'minutes' => $file->minutes,
        ];
    }

    private function createSentences(Story $story, StoryFile $file): void
    {
        foreach ($file->sentences as $sentencePosition => $sentence) {
            $storedSentence = $story->sentences()->create([
                'position' => $sentencePosition,
                'text' => $sentence['text'],
            ]);

            foreach ($sentence['words'] as $wordPosition => $word) {
                $storedSentence->words()->create([...$word, 'position' => $wordPosition]);
            }
        }
    }

    /**
     * @return list<list<string>>
     */
    private function wordTextsOf(Story $story): array
    {
        $wordTexts = [];

        foreach ($story->sentences as $sentence) {
            $wordTexts[] = array_values($sentence->words->map(fn (Word $word): string => $word->text)->all());
        }

        return $wordTexts;
    }

    /**
     * @return list<list<string>>
     */
    private function wordTextsOfFile(StoryFile $file): array
    {
        return array_map(
            fn (array $sentence): array => array_column($sentence['words'], 'text'),
            $file->sentences,
        );
    }
}
