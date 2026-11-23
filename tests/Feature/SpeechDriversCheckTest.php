<?php

use App\Speech\ChirpSpeechToText;
use App\Speech\FakeSpeechToText;
use App\Speech\FakeTextToSpeech;
use App\Speech\GeminiSpeechToText;
use App\Speech\GeminiTextToSpeech;
use App\Speech\SpeechToText;
use App\Speech\TextToSpeech;

/**
 * Choose the drivers and their settings, as the server's environment would.
 *
 * @param  array<string, mixed>  $settings
 */
function speechSettings(array $settings): void
{
    config([
        'services.gemini.api_key' => 'test-gemini-key',
        'services.speech_to_text.chirp.credentials' => __FILE__,
        ...$settings,
    ]);

    app()->forgetInstance(SpeechToText::class);
    app()->forgetInstance(TextToSpeech::class);
}

function inProduction(): void
{
    app()->detectEnvironment(fn (): string => 'production');
}

test('the drivers are chosen by the settings', function (string $listening, string $voice, string $listener, string $speaker) {
    speechSettings(['services.speech_to_text.driver' => $listening, 'services.text_to_speech.driver' => $voice]);

    expect(app(SpeechToText::class))->toBeInstanceOf($listener)
        ->and(app(TextToSpeech::class))->toBeInstanceOf($speaker);
})->with([
    'the fakes' => ['fake', 'fake', FakeSpeechToText::class, FakeTextToSpeech::class],
    'Gemini' => ['gemini', 'gemini', GeminiSpeechToText::class, GeminiTextToSpeech::class],
    'Chirp to listen' => ['chirp', 'gemini', ChirpSpeechToText::class, GeminiTextToSpeech::class],
]);

test('production starts with the real drivers', function (string $listening) {
    inProduction();
    speechSettings(['services.speech_to_text.driver' => $listening, 'services.text_to_speech.driver' => 'gemini']);

    $this->artisan('speech:check')->assertSuccessful();
})->with(['gemini', 'chirp']);

test('production refuses to start with a fake driver', function (string $listening, string $voice) {
    inProduction();
    speechSettings(['services.speech_to_text.driver' => $listening, 'services.text_to_speech.driver' => $voice]);

    $this->artisan('speech:check')
        ->expectsOutputToContain('fake')
        ->assertFailed();
})->with([
    'fake listening' => ['fake', 'gemini'],
    'a fake voice' => ['gemini', 'fake'],
    'both fake' => ['fake', 'fake'],
]);

test('the fakes are fine outside production', function () {
    speechSettings(['services.speech_to_text.driver' => 'fake', 'services.text_to_speech.driver' => 'fake']);

    $this->artisan('speech:check')->assertSuccessful();
});

test('a real driver without its settings refuses to start', function (array $settings, string $missing) {
    inProduction();
    speechSettings([
        'services.speech_to_text.driver' => 'gemini',
        'services.text_to_speech.driver' => 'gemini',
        ...$settings,
    ]);

    $this->artisan('speech:check')
        ->expectsOutputToContain($missing)
        ->assertFailed();
})->with([
    'no Gemini key' => [['services.gemini.api_key' => ''], 'GEMINI_API_KEY'],
    'no Chirp key file' => [['services.speech_to_text.driver' => 'chirp', 'services.speech_to_text.chirp.credentials' => __DIR__.'/missing-key-file.json'], 'GOOGLE_APPLICATION_CREDENTIALS'],
    'an unknown driver' => [['services.speech_to_text.driver' => 'whisper'], 'whisper'],
]);
