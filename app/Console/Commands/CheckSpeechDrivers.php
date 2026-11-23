<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The start-up check of the speech-to-text and voice drivers, run by the
 * container before it serves anything (docker/start-container.sh). In
 * production the fakes are refused: the fake listener scores every
 * sentence as perfect, so a misconfigured server must fail loudly rather
 * than mislead Parents. A real driver without its key is refused anywhere.
 * Nothing here calls a service.
 */
#[Signature('speech:check')]
#[Description('Refuse to start with a fake speech driver in production, or a real one without its settings')]
class CheckSpeechDrivers extends Command
{
    /**
     * The drivers each seam knows.
     */
    private const array DRIVERS = [
        'SPEECH_TO_TEXT_DRIVER' => ['services.speech_to_text.driver', ['fake', 'gemini', 'chirp']],
        'TEXT_TO_SPEECH_DRIVER' => ['services.text_to_speech.driver', ['fake', 'gemini']],
    ];

    public function handle(): int
    {
        $problems = [];

        foreach (self::DRIVERS as $setting => [$configKey, $known]) {
            $driver = (string) config($configKey);

            $problems[] = match (true) {
                ! in_array($driver, $known, true) => "{$setting} is [{$driver}], which is not one of: ".implode(', ', $known).'.',
                $driver === 'fake' && $this->laravel->isProduction() => "{$setting} is [fake] in production. The fake hears every sentence read perfectly; set a real driver.",
                $driver === 'gemini' && blank(config('services.gemini.api_key')) => "{$setting} is [gemini] but GEMINI_API_KEY is not set.",
                $driver === 'chirp' && ! is_file((string) config('services.speech_to_text.chirp.credentials')) => "{$setting} is [chirp] but GOOGLE_APPLICATION_CREDENTIALS does not name a key file.",
                default => null,
            };
        }

        $problems = array_filter($problems);

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        if ($problems !== []) {
            return self::FAILURE;
        }

        $this->info('Speech drivers: listening ['.config('services.speech_to_text.driver').'], voice ['.config('services.text_to_speech.driver').'].');

        return self::SUCCESS;
    }
}
