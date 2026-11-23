<?php

use App\Models\Child;
use App\Models\Reading;
use App\Models\Story;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The two sentences of the twenty-word fixture Story, ten words each.
 */
const TWENTY_WORDS_FIRST = "Bir kuni Ali bilan Vali bog'ga borib katta olma terdi";
const TWENTY_WORDS_SECOND = 'Keyin ular uyga qaytib onasiga olmalarni berib katta rahmat eshitdi';

/**
 * Read one sentence of the Reading aloud, as the reading screen sends it.
 */
function readSentenceAloud(Child $child, Reading $reading, int $position, int $durationMs = 30000): mixed
{
    return actingAsChild($child)->postJson(route('readings.sentences.store', $reading), [
        'sentence' => $position,
        'audio' => UploadedFile::fake()->createWithContent('sentence.webm', 'fake-opus-bytes'),
        'duration_ms' => $durationMs,
    ]);
}

/**
 * Drop the given words from a sentence, as if the Child misread them.
 *
 * @param  list<string>  $misread
 */
function misreading(string $sentence, array $misread): string
{
    return implode(' ', array_diff(explode(' ', $sentence), $misread));
}

beforeEach(function () {
    $this->artisan('stories:load', ['--path' => base_path('tests/Fixtures/twenty-words')])->assertSuccessful();
    $this->story = Story::firstWhere('slug', 'yigirma-soz');
    $this->child = Child::factory()->create();
});

test('reading the last sentence finishes the reading and shows its result', function () {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create();
    speech()->hears(TWENTY_WORDS_FIRST, misreading(TWENTY_WORDS_SECOND, ['onasiga']));

    readSentenceAloud($this->child, $reading, 0, 30000)->assertOk();
    expect($reading->refresh()->finished_at)->toBeNull();

    $this->freezeSecond();
    readSentenceAloud($this->child, $reading, 1, 30000)->assertOk();

    $reading->refresh();
    expect($reading->finished_at?->equalTo(now()))->toBeTrue();

    actingAsChild($this->child)
        ->get(route('readings.show', $reading))
        ->assertRedirect(route('readings.result', $reading));

    actingAsChild($this->child)
        ->get(route('readings.result', $reading))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('child/result')
            ->where('story', ['id' => $this->story->id, 'title' => "Yigirma so'z"])
            ->where('result', [
                'stars' => 3,
                'accuracy' => 95,
                'readingSpeed' => 19,
                'wordsToPractise' => ['onasiga'],
            ]),
        );
});

test('stars come from accuracy: 3 at 95% or more, 2 at 85% or more, otherwise 1', function (array $misread, int $accuracy, int $stars) {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create();
    speech()->hears(TWENTY_WORDS_FIRST, misreading(TWENTY_WORDS_SECOND, $misread) ?: 'salom');

    readSentenceAloud($this->child, $reading, 0);
    readSentenceAloud($this->child, $reading, 1);

    actingAsChild($this->child)
        ->get(route('readings.result', $reading))
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.accuracy', $accuracy)
            ->where('result.stars', $stars)
            ->where('result.wordsToPractise', $misread),
        );
})->with([
    'every word right' => [[], 100, 3],
    'exactly 95%' => [['onasiga'], 95, 3],
    'just under 95%' => [['uyga', 'onasiga'], 90, 2],
    'exactly 85%' => [['ular', 'uyga', 'onasiga'], 85, 2],
    'just under 85%' => [['ular', 'uyga', 'qaytib', 'onasiga'], 80, 1],
    'nothing right' => [['Keyin', 'ular', 'uyga', 'qaytib', 'onasiga', 'olmalarni', 'berib', 'katta', 'rahmat', 'eshitdi'], 50, 1],
]);

test('reading speed is the words correct on the first try per minute of reading time', function () {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create();
    speech()->hears(misreading(TWENTY_WORDS_FIRST, ['Vali', 'terdi']), TWENTY_WORDS_SECOND);

    readSentenceAloud($this->child, $reading, 0, 20000);
    readSentenceAloud($this->child, $reading, 1, 25000);

    actingAsChild($this->child)
        ->get(route('readings.result', $reading))
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.readingSpeed', 24)
            ->where('result.wordsToPractise', ['Vali', 'terdi']),
        );
});

test('words to practise are listed once each, in story order, without punctuation', function () {
    $this->artisan('stories:load', ['--path' => base_path('tests/Fixtures/stories')])->assertSuccessful();
    $story = Story::firstWhere('slug', 'ali-va-mushuk');
    $reading = Reading::factory()->for($this->child)->for($story)->create();
    speech()->hears('uyga keldi', "Mushuk o'yinchoq bilan o'ynadi", 'Salom mushuk dedi');

    readSentenceAloud($this->child, $reading, 0);
    readSentenceAloud($this->child, $reading, 1);
    readSentenceAloud($this->child, $reading, 2);

    actingAsChild($this->child)
        ->get(route('readings.result', $reading))
        ->assertInertia(fn (Assert $page) => $page->where('result.wordsToPractise', ['Ali']));
});

test('an unfinished reading has no result yet', function () {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create(['next_sentence_position' => 1]);

    actingAsChild($this->child)
        ->get(route('readings.result', $reading))
        ->assertRedirect(route('readings.show', $reading));
});

test("another child's result cannot be seen", function () {
    $siblingsReading = Reading::factory()->for(Child::factory()->for($this->child->parent, 'parent'))->for($this->story)->finished()->create();

    actingAsChild($this->child)->get(route('readings.result', $siblingsReading))->assertNotFound();
});

test('the result offers the next story in library order (level, then title), or none after the last one', function () {
    $this->artisan('stories:load', ['--path' => base_path('tests/Fixtures/stories')])->assertSuccessful();
    $kichikQush = Story::firstWhere('slug', 'kichik-qush');
    $dengiz = Story::firstWhere('slug', 'dengiz-boyida');

    $first = Reading::factory()->for($this->child)->for($this->story)->finished()->create(['stars' => 2, 'accuracy' => 0.9, 'reading_speed' => 30]);
    $last = Reading::factory()->for($this->child)->for($dengiz)->finished()->create(['stars' => 2, 'accuracy' => 0.9, 'reading_speed' => 30]);

    actingAsChild($this->child)
        ->get(route('readings.result', $first))
        ->assertInertia(fn (Assert $page) => $page->where('nextStory', ['id' => $kichikQush->id, 'title' => 'Kichik qush']));

    actingAsChild($this->child)
        ->get(route('readings.result', $last))
        ->assertInertia(fn (Assert $page) => $page->where('nextStory', null));
});

test('the library shows the best stars per story, and the stars total adds up only the best per story', function () {
    $this->artisan('stories:load', ['--path' => base_path('tests/Fixtures/stories')])->assertSuccessful();
    $ali = Story::firstWhere('slug', 'ali-va-mushuk');
    $kichikQush = Story::firstWhere('slug', 'kichik-qush');
    $sibling = Child::factory()->for($this->child->parent, 'parent')->create();

    Reading::factory()->for($this->child)->for($ali)->finished()->create(['stars' => 1]);
    Reading::factory()->for($this->child)->for($ali)->finished()->create(['stars' => 3]);
    Reading::factory()->for($this->child)->for($ali)->finished()->create(['stars' => 2]);
    Reading::factory()->for($this->child)->for($kichikQush)->finished()->create(['stars' => 2]);
    Reading::factory()->for($this->child)->for($this->story)->create(['next_sentence_position' => 1]);
    Reading::factory()->for($sibling)->for($this->story)->finished()->create(['stars' => 3]);

    actingAsChild($this->child)
        ->get(route('stories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('starsTotal', 5)
            ->where('stories.0.title', 'Ali va mushuk')
            ->where('stories.0.bestStars', 3)
            ->where('stories.1.title', "Yigirma so'z")
            ->where('stories.1.bestStars', null)
            ->where('stories.2.title', 'Kichik qush')
            ->where('stories.2.bestStars', 2)
            ->where('stories.3.bestStars', null),
        );
});

test('a re-read can raise the best stars but never lowers them', function () {
    $first = Reading::factory()->for($this->child)->for($this->story)->create();
    speech()->hears(misreading(TWENTY_WORDS_FIRST, ['Vali', 'terdi']), TWENTY_WORDS_SECOND);
    readSentenceAloud($this->child, $first, 0);
    readSentenceAloud($this->child, $first, 1);

    actingAsChild($this->child)->get(route('stories.index'))
        ->assertInertia(fn (Assert $page) => $page->where('stories.0.bestStars', 2)->where('starsTotal', 2));

    actingAsChild($this->child)->post(route('readings.store', $this->story));
    $better = Reading::query()->unfinished()->sole();
    readSentenceAloud($this->child, $better, 0);
    readSentenceAloud($this->child, $better, 1);

    actingAsChild($this->child)->get(route('stories.index'))
        ->assertInertia(fn (Assert $page) => $page->where('stories.0.bestStars', 3)->where('starsTotal', 3));

    actingAsChild($this->child)->post(route('readings.store', $this->story));
    $worse = Reading::query()->unfinished()->sole();
    speech()->hears('salom', 'salom');
    readSentenceAloud($this->child, $worse, 0);
    readSentenceAloud($this->child, $worse, 1);

    actingAsChild($this->child)->get(route('readings.result', $worse))
        ->assertInertia(fn (Assert $page) => $page->where('result.stars', 1));
    actingAsChild($this->child)->get(route('stories.index'))
        ->assertInertia(fn (Assert $page) => $page->where('stories.0.bestStars', 3)->where('starsTotal', 3));
});

test('an unfinished reading gives no stars', function () {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create();
    readSentenceAloud($this->child, $reading, 0);

    actingAsChild($this->child)->get(route('stories.index'))
        ->assertInertia(fn (Assert $page) => $page->where('stories.0.bestStars', null)->where('starsTotal', 0));
});
