<?php

use App\Speech\AishaSpeechToText;
use App\Speech\AishaTextToSpeech;
use App\Speech\ChirpSpeechToText;
use App\Speech\FakeSpeechToText;
use App\Speech\FakeTextToSpeech;
use App\Speech\GeminiSpeechToText;
use App\Speech\GeminiTextToSpeech;
use App\Speech\SpeakingOptions;
use App\Speech\SpeechToText;
use App\Speech\TextToSpeech;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Choose the drivers and their settings, as the server's environment would.
 *
 * @param  array<string, mixed>  $settings
 */
function speechSettings(array $settings): void
{
    config([
        'services.gemini.api_key' => 'test-gemini-key',
        'services.aisha.api_key' => 'test-aisha-key',
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
    'Aisha' => ['aisha', 'aisha', AishaSpeechToText::class, AishaTextToSpeech::class],
]);

test('production starts with the real drivers', function (string $listening) {
    inProduction();
    speechSettings(['services.speech_to_text.driver' => $listening, 'services.text_to_speech.driver' => 'gemini']);

    $this->artisan('speech:check')->assertSuccessful();
})->with(['gemini', 'chirp', 'aisha']);

test('production starts with the Aisha voice', function () {
    inProduction();
    speechSettings(['services.speech_to_text.driver' => 'gemini', 'services.text_to_speech.driver' => 'aisha']);

    $this->artisan('speech:check')->assertSuccessful();
});

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
    'no Aisha key to listen' => [['services.speech_to_text.driver' => 'aisha', 'services.aisha.api_key' => ''], 'AISHA_API_KEY'],
    'no Aisha key for the voice' => [['services.text_to_speech.driver' => 'aisha', 'services.aisha.api_key' => ''], 'AISHA_API_KEY'],
    'no Chirp key file' => [['services.speech_to_text.driver' => 'chirp', 'services.speech_to_text.chirp.credentials' => __DIR__.'/missing-key-file.json'], 'GOOGLE_APPLICATION_CREDENTIALS'],
    'an unknown driver' => [['services.speech_to_text.driver' => 'whisper'], 'whisper'],
]);

test('the Aisha drivers call the VoiceLab developer API at the configured base URL, with the configured model, voice and pace', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://voicelab.example/v1/tts' => Http::response("RIFF\x24\x00\x00\x00WAVEfmt ", 200, ['Content-Type' => 'audio/wav']),
        'https://voicelab.example/v1/stt' => Http::response(['id' => 'stt_01JTEST', 'status' => 'queued'], 202),
        'https://voicelab.example/v1/stt/transcriptions/stt_01JTEST' => Http::response(['id' => 'stt_01JTEST', 'status' => 'completed', 'transcript' => 'uyga']),
    ]);
    speechSettings([
        'services.speech_to_text.driver' => 'aisha',
        'services.text_to_speech.driver' => 'aisha',
        'services.aisha.api_key' => 'vlk_test-key',
        'services.aisha.base_url' => 'https://voicelab.example',
        'services.text_to_speech.aisha.model' => 'lison-v2',
        'services.text_to_speech.aisha.voice' => 'voice_01JUZBEK',
        'services.text_to_speech.aisha.slow_speed' => 0.7,
    ]);

    app(TextToSpeech::class)->speak(['uyga'], new SpeakingOptions(slowly: true));
    app(SpeechToText::class)->transcribe('RIFF-clip', 'audio/wav', 'uyga', 'uz');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://voicelab.example/v1/tts'
        && $request->header('Authorization') === ['Bearer vlk_test-key']
        && $request['model_id'] === 'lison-v2' && $request['voice_id'] === 'voice_01JUZBEK' && $request['speed'] === 0.7);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://voicelab.example/v1/stt'
        && $request->header('Authorization') === ['Bearer vlk_test-key']);
});
