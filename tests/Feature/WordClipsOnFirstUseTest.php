<?php

use App\Models\Child;
use App\Models\Reading;
use App\Models\Story;
use App\Speech\WordClips;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->artisan('stories:load', ['--path' => base_path('tests/Fixtures/stories')])->assertSuccessful();
    $this->story = Story::firstWhere('slug', 'ali-va-mushuk');
    $this->child = Child::factory()->create();
    $this->reading = Reading::factory()->for($this->child)->for($this->story)->create();
});

test('a word misread for the first time has its clip made in one voice request, after the sentence answer', function () {
    speech()->hears('Ali uyda keldi');

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0))
        ->assertOk()
        ->assertJsonPath('pendingWord.stage', 'retry')
        ->assertJsonPath('pendingWord.clip', null);

    expect(voice()->requests())->toBe([
        ['parts' => ['uy', 'ga', 'uyga'], 'slowly' => true, 'pause' => 'long', 'language' => 'uz'],
    ]);
    Storage::disk('public')->assertExists('audio/clips/uy-ga.wav');
});

test('a word that already has its clip is not voiced again, for any Child', function () {
    speech()->hears('Ali uyda keldi', 'Ali uyda keldi');
    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0));

    $otherChild = Child::factory()->create();
    $otherReading = Reading::factory()->for($otherChild)->for($this->story)->create();

    actingAsChild($otherChild)
        ->postJson(route('readings.sentences.store', $otherReading), sentenceClip(0))
        ->assertOk()
        ->assertJsonPath('pendingWord.clip', '/storage/audio/clips/uy-ga.wav');

    expect(voice()->requests())->toHaveCount(1);
});

test('a word another request is voicing at the same moment is not voiced twice', function () {
    $lock = Cache::lock('word-clip|audio/clips/uy-ga.wav', 60);
    $lock->get();
    speech()->hears('Ali uyda keldi');

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0))
        ->assertOk();

    expect(voice()->requests())->toBe([]);
    $lock->release();
});

/**
 * Bring the Reading to Syllable help for "uyga" while the voice service fails, so the word has no clip yet.
 */
function openSyllableHelpWithoutClip(Child $child, Reading $reading): void
{
    voice()->fails();
    speech()->hears('Ali uyda keldi', 'uyda');
    actingAsChild($child)->postJson(route('readings.sentences.store', $reading), sentenceClip(0))->assertOk();
    actingAsChild($child)
        ->postJson(route('readings.retries.store', $reading), wordClip(0, 1))
        ->assertOk()
        ->assertJsonPath('pendingWord.stage', 'syllable_help')
        ->assertJsonPath('pendingWord.clip', null);
}

test('a voice failure leaves the reading flow unaffected and the word without a clip', function () {
    openSyllableHelpWithoutClip($this->child, $this->reading);

    Storage::disk('public')->assertMissing('audio/clips/uy-ga.wav');

    actingAsChild($this->child)
        ->postJson(route('readings.syllable-help.store', $this->reading), ['sentence' => 0, 'word' => 1])
        ->assertOk()
        ->assertJsonPath('status', 'done');
});

test('Syllable help asks for a missing clip without a reload, and gets it once a later try makes it', function () {
    openSyllableHelpWithoutClip($this->child, $this->reading);
    voice()->fails();

    $this->travel(WordClips::FIRST_WAIT_SECONDS + 1)->seconds();

    actingAsChild($this->child)
        ->getJson(route('readings.clip.show', $this->reading))
        ->assertOk()
        ->assertExactJson(['clip' => null]);

    $this->travel(2 * WordClips::FIRST_WAIT_SECONDS + 1)->seconds();

    actingAsChild($this->child)
        ->getJson(route('readings.clip.show', $this->reading))
        ->assertOk()
        ->assertExactJson(['clip' => null]);

    actingAsChild($this->child)
        ->getJson(route('readings.clip.show', $this->reading))
        ->assertOk()
        ->assertExactJson(['clip' => '/storage/audio/clips/uy-ga.wav']);

    expect(voice()->requests())->toHaveCount(3);
});

test('a word waiting after a failure is not tried again before its wait is over', function () {
    openSyllableHelpWithoutClip($this->child, $this->reading);

    actingAsChild($this->child)->getJson(route('readings.clip.show', $this->reading))->assertExactJson(['clip' => null]);

    expect(voice()->requests())->toHaveCount(1);
});

test('only the active Child reading the Story can ask for its pending word\'s clip', function () {
    speech()->hears('Ali uyda keldi');
    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0));
    $sibling = Child::factory()->for($this->child->parent, 'parent')->create();

    $strangersChild = Child::factory()->create();

    actingAsChild($sibling)->getJson(route('readings.clip.show', $this->reading))->assertNotFound();
    actingAsChild($strangersChild)->getJson(route('readings.clip.show', $this->reading))->assertNotFound();
    expect(voice()->requests())->toHaveCount(1);

    auth()->logout();
    $this->getJson(route('readings.clip.show', $this->reading))->assertUnauthorized();
});

test('there is no clip to ask for while no word is pending', function () {
    actingAsChild($this->child)->getJson(route('readings.clip.show', $this->reading))->assertConflict();

    expect(voice()->requests())->toBe([]);
});

test('a Child can ask for the clip 30 times a minute', function () {
    speech()->hears('Ali uyda keldi');
    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0));

    foreach (range(1, 30) as $ask) {
        actingAsChild($this->child)->getJson(route('readings.clip.show', $this->reading))->assertOk();
    }

    actingAsChild($this->child)->getJson(route('readings.clip.show', $this->reading))->assertTooManyRequests();
});

test('a rate-limited voice request never reaches the Child, and the word is tried again once Retry-After has passed', function () {
    voice()->rateLimited(retryAfterSeconds: 120);
    speech()->hears('Ali uyda keldi', 'uyda');
    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0))->assertOk();
    actingAsChild($this->child)->postJson(route('readings.retries.store', $this->reading), wordClip(0, 1))->assertOk();

    $this->travel(60)->seconds();
    actingAsChild($this->child)->getJson(route('readings.clip.show', $this->reading))->assertExactJson(['clip' => null]);
    expect(voice()->requests())->toHaveCount(1);

    $this->travel(61)->seconds();
    actingAsChild($this->child)->getJson(route('readings.clip.show', $this->reading))->assertExactJson(['clip' => null]);
    actingAsChild($this->child)
        ->getJson(route('readings.clip.show', $this->reading))
        ->assertExactJson(['clip' => '/storage/audio/clips/uy-ga.wav']);
    expect(voice()->requests())->toHaveCount(2);
});
