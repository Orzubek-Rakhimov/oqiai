<?php

use App\Http\Middleware\EnsureActiveChild;
use App\Models\Child;
use App\Speech\FakeSpeechToText;
use App\Speech\FakeTextToSpeech;
use App\Speech\SpeechToText;
use App\Speech\TextToSpeech;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Helpers shared by the feature tests.
|
*/

/**
 * Log in as the Child's Parent with the Child chosen on the picker.
 */
function actingAsChild(Child $child): mixed
{
    return test()->actingAs($child->parent)
        ->withSession([EnsureActiveChild::SESSION_KEY => $child->id]);
}

/**
 * Get the speech-to-text fake bound for every test, to script what the Child says.
 */
function speech(): FakeSpeechToText
{
    $fake = app(SpeechToText::class);

    assert($fake instanceof FakeSpeechToText);

    return $fake;
}

/**
 * Get the text-to-speech fake bound for every test, to see what it was asked to read or make it fail.
 */
function voice(): FakeTextToSpeech
{
    $fake = app(TextToSpeech::class);

    assert($fake instanceof FakeTextToSpeech);

    return $fake;
}

/**
 * The form the reading screen sends for one sentence: which sentence, the clip and its length.
 *
 * @return array{sentence: int, audio: UploadedFile, duration_ms: int}
 */
function sentenceClip(int $position, int $durationMs = 1500): array
{
    return [
        'sentence' => $position,
        'audio' => UploadedFile::fake()->createWithContent('sentence.webm', 'fake-opus-bytes'),
        'duration_ms' => $durationMs,
    ];
}

/**
 * The form the reading screen sends for one retried word: which word of which sentence, the clip and its length.
 *
 * @return array{sentence: int, word: int, audio: UploadedFile, duration_ms: int}
 */
function wordClip(int $sentence, int $word, int $durationMs = 800): array
{
    return [
        'sentence' => $sentence,
        'word' => $word,
        'audio' => UploadedFile::fake()->createWithContent('word.webm', 'fake-opus-bytes'),
        'duration_ms' => $durationMs,
    ];
}
