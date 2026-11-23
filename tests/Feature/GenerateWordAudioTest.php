<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
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

test('generating the audio saves a slow syllables clip and a slow whole-word clip for every Story word', function () {
    $this->artisan('audio:generate')
        ->expectsOutputToContain('Created')
        ->assertSuccessful();

    Storage::disk('public')->assertExists([
        'audio/syllables/uy-ga.wav',
        'audio/words/uyga.wav',
        'audio/syllables/o_-yin-choq.wav',
        'audio/words/o_yinchoq.wav',
        'audio/syllables/den-giz.wav',
        'audio/words/dengiz.wav',
        'audio/syllables/qush.wav',
        'audio/words/qush.wav',
    ]);

    expect(voice()->requests())->toContain(
        ['parts' => ['uy', 'ga'], 'slowly' => true, 'pause' => 'long', 'language' => 'uz'],
        ['parts' => ['uyga'], 'slowly' => true, 'pause' => 'long', 'language' => 'uz'],
    );
});

test('a word in several Stories, or several times in one, is voiced once and shares its clips', function () {
    $this->artisan('audio:generate')->assertSuccessful();

    $texts = collect(voicedTexts());

    expect($texts->filter(fn (string $text) => $text === 'kutubxonaga')->count())->toBe(1)
        ->and($texts->filter(fn (string $text) => $text === 'ku | tub | xo | na | ga')->count())->toBe(1)
        ->and($texts->filter(fn (string $text) => $text === 'mushuk')->count())->toBe(1);
});

test('clips that already exist are skipped', function () {
    Storage::disk('public')->put('audio/words/uyga.wav', 'old clip');

    $this->artisan('audio:generate')->assertSuccessful();

    expect(Storage::disk('public')->get('audio/words/uyga.wav'))->toBe('old clip')
        ->and(voicedTexts())->not->toContain('uyga')
        ->and(voicedTexts())->toContain('uy | ga');

    $requestsSoFar = count(voice()->requests());

    $this->artisan('audio:generate')
        ->expectsOutputToContain('Created 0 clips')
        ->assertSuccessful();

    expect(voice()->requests())->toHaveCount($requestsSoFar);
});

test('the clips of one named word can be regenerated, whatever its capitals and apostrophe', function () {
    $this->artisan('audio:generate')->assertSuccessful();
    Storage::disk('public')->put('audio/words/o_yinchoq.wav', 'old clip');
    Storage::disk('public')->put('audio/syllables/o_-yin-choq.wav', 'old clip');
    Storage::disk('public')->put('audio/words/uyga.wav', 'old clip');
    $requestsSoFar = count(voice()->requests());

    $this->artisan('audio:generate', ['word' => 'O‘yinchoq'])
        ->expectsOutputToContain('Created 2 clips')
        ->assertSuccessful();

    expect(Storage::disk('public')->get('audio/words/o_yinchoq.wav'))->not->toBe('old clip')
        ->and(Storage::disk('public')->get('audio/syllables/o_-yin-choq.wav'))->not->toBe('old clip')
        ->and(Storage::disk('public')->get('audio/words/uyga.wav'))->toBe('old clip')
        ->and(array_slice(voicedTexts(), $requestsSoFar))->toBe(["o' | yin | choq", "o'yinchoq"]);
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
