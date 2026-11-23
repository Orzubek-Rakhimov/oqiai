<?php

namespace App\Providers;

use App\Speech\ChirpSpeechToText;
use App\Speech\FakeSpeechToText;
use App\Speech\FakeTextToSpeech;
use App\Speech\GeminiSpeechToText;
use App\Speech\GeminiTextToSpeech;
use App\Speech\SpeechToText;
use App\Speech\TextToSpeech;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SpeechToText::class, fn (): SpeechToText => match (config('services.speech_to_text.driver')) {
            'fake' => new FakeSpeechToText,
            'gemini' => new GeminiSpeechToText(
                apiKey: (string) config('services.gemini.api_key'),
                model: (string) config('services.speech_to_text.gemini.model'),
                timeoutSeconds: (int) config('services.speech_to_text.timeout'),
            ),
            'chirp' => new ChirpSpeechToText(
                keyFile: (string) config('services.speech_to_text.chirp.credentials'),
                model: (string) config('services.speech_to_text.chirp.model'),
                location: (string) config('services.speech_to_text.chirp.location'),
                project: config('services.speech_to_text.chirp.project') ?: null,
                timeoutSeconds: (int) config('services.speech_to_text.timeout'),
            ),
            default => throw new InvalidArgumentException('Unknown speech-to-text driver ['.config('services.speech_to_text.driver').'].'),
        });

        $this->app->singleton(TextToSpeech::class, fn (): TextToSpeech => match (config('services.text_to_speech.driver')) {
            'fake' => new FakeTextToSpeech,
            'gemini' => new GeminiTextToSpeech(
                apiKey: (string) config('services.gemini.api_key'),
                model: (string) config('services.text_to_speech.gemini.model'),
                voice: (string) config('services.text_to_speech.gemini.voice'),
                timeoutSeconds: (int) config('services.text_to_speech.timeout'),
            ),
            default => throw new InvalidArgumentException('Unknown text-to-speech driver ['.config('services.text_to_speech.driver').'].'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Limit the clips each Parent account sends to the paid speech-to-text
     * service: sentences, retries and microphone checks share one budget.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('speech-to-text', fn (Request $request): Limit => Limit::perMinute(30)
            ->by('speech-to-text|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
