<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Storage::fake('public');
    Sleep::fake();
    $this->artisan('stories:load', ['--path' => base_path('tests/Fixtures/stories')])->assertSuccessful();
});

/**
 * Get the text the voice service was asked to read in each request, parts joined by " | ".
 *
 * @return list<string>
 */
function voicedTexts(): array
{
    return array_map(fn (array $request): string => implode(' | ', $request['parts']), voice()->requests());
}

test('generating the audio ahead is optional and saves one clip per Story word: its syllables read slowly, then the word', function () {
    $this->artisan('audio:generate')
        ->expectsOutputToContain('Created')
        ->assertSuccessful();

    Storage::disk('public')->assertExists([
        'audio/clips/uy-ga.wav',
        'audio/clips/o_-yin-choq.wav',
        'audio/clips/den-giz.wav',
        'audio/clips/qush.wav',
    ]);

    expect(voice()->requests())->toContain(
        ['parts' => ['uy', 'ga', 'uyga'], 'slowly' => true, 'pause' => 'long', 'language' => 'uz'],
        ['parts' => ['qush', 'qush'], 'slowly' => true, 'pause' => 'long', 'language' => 'uz'],
    );
});

test('a word in several Stories, or several times in one, is voiced once and shares its clip', function () {
    $this->artisan('audio:generate')->assertSuccessful();

    $texts = collect(voicedTexts());

    expect($texts->filter(fn (string $text) => $text === 'ku | tub | xo | na | ga | kutubxonaga')->count())->toBe(1)
        ->and($texts->filter(fn (string $text) => $text === 'mu | shuk | mushuk')->count())->toBe(1)
        ->and($texts)->toHaveCount(count(Storage::disk('public')->allFiles()));
});

test('clips that already exist, such as those made on first use, are skipped', function () {
    Storage::disk('public')->put('audio/clips/uy-ga.wav', 'old clip');

    $this->artisan('audio:generate')->assertSuccessful();

    expect(Storage::disk('public')->get('audio/clips/uy-ga.wav'))->toBe('old clip')
        ->and(voicedTexts())->not->toContain('uy | ga | uyga');

    $requestsSoFar = count(voice()->requests());

    $this->artisan('audio:generate')
        ->expectsOutputToContain('Created 0 clips')
        ->assertSuccessful();

    expect(voice()->requests())->toHaveCount($requestsSoFar);
});

test('the clip of one named word can be regenerated, whatever its capitals and apostrophe', function () {
    $this->artisan('audio:generate')->assertSuccessful();
    Storage::disk('public')->put('audio/clips/o_-yin-choq.wav', 'old clip');
    Storage::disk('public')->put('audio/clips/uy-ga.wav', 'old clip');
    $requestsSoFar = count(voice()->requests());

    $this->artisan('audio:generate', ['word' => 'O‘yinchoq'])
        ->expectsOutputToContain('Created 1 clip')
        ->assertSuccessful();

    expect(Storage::disk('public')->get('audio/clips/o_-yin-choq.wav'))->not->toBe('old clip')
        ->and(Storage::disk('public')->get('audio/clips/uy-ga.wav'))->toBe('old clip')
        ->and(array_slice(voicedTexts(), $requestsSoFar))->toBe(["o' | yin | choq | o'yinchoq"]);
});

test('regenerating a word that is in no Story fails with a message', function () {
    $this->artisan('audio:generate', ['word' => 'samolyot'])
        ->expectsOutputToContain('samolyot')
        ->assertFailed();

    expect(voice()->requests())->toBe([]);
});

test('a clip the voice service fails on is not saved, and the rest are still generated', function () {
    voice()->fails();

    $this->artisan('audio:generate')
        ->expectsOutputToContain('failed')
        ->assertFailed();

    expect(Storage::disk('public')->allFiles())->toHaveCount(count(voice()->requests()) - 1);

    $this->artisan('audio:generate')
        ->expectsOutputToContain('Created 1 clip')
        ->assertSuccessful();
});

test('when the voice service is rate limited, the command waits as long as it asks and tries the word again', function () {
    voice()->rateLimited(retryAfterSeconds: 25)->rateLimited();

    $this->artisan('audio:generate', ['word' => 'uyga'])
        ->expectsOutputToContain('Created 1 clip')
        ->assertSuccessful();

    Storage::disk('public')->assertExists('audio/clips/uy-ga.wav');
    expect(voice()->requests())->toHaveCount(3);
    Sleep::assertSequence([Sleep::for(25)->seconds(), Sleep::for(60)->seconds()]);
});

test('a word still rate limited after several waits is reported as failed', function () {
    foreach (range(1, 5) as $try) {
        voice()->rateLimited(retryAfterSeconds: 10);
    }

    $this->artisan('audio:generate', ['word' => 'uyga'])
        ->expectsOutputToContain('failed')
        ->assertFailed();

    Storage::disk('public')->assertMissing('audio/clips/uy-ga.wav');
    expect(voice()->requests())->toHaveCount(5);
});
