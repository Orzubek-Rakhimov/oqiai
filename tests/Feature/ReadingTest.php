<?php

use App\Enums\WordOutcome;
use App\Models\Child;
use App\Models\Reading;
use App\Models\Story;
use App\Models\WordResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

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

beforeEach(function () {
    $this->artisan('stories:load', ['--path' => base_path('tests/Fixtures/stories')])->assertSuccessful();
    $this->story = Story::firstWhere('slug', 'ali-va-mushuk');
    $this->child = Child::factory()->create();
});

test('opening a story starts a reading at its first sentence', function () {
    $response = actingAsChild($this->child)->post(route('readings.store', $this->story));

    $reading = Reading::sole();
    expect($reading->child_id)->toBe($this->child->id)
        ->and($reading->story_id)->toBe($this->story->id)
        ->and($reading->finished_at)->toBeNull();

    $response->assertRedirect(route('readings.show', $reading));

    actingAsChild($this->child)
        ->get(route('readings.show', $reading))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('child/reading')
            ->where('reading.id', $reading->id)
            ->where('story.title', 'Ali va mushuk')
            ->where('progress', ['read' => 0, 'total' => 3])
            ->where('sentence.position', 0)
            ->where('sentence.words', ['Ali', 'uyga', 'keldi.']),
        );
});

test("opening a story again resumes the child's unfinished reading at the next sentence", function () {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create(['next_sentence_position' => 2]);

    actingAsChild($this->child)
        ->post(route('readings.store', $this->story))
        ->assertRedirect(route('readings.show', $reading));

    expect(Reading::count())->toBe(1);

    actingAsChild($this->child)
        ->get(route('readings.show', $reading))
        ->assertInertia(fn (Assert $page) => $page
            ->where('progress', ['read' => 2, 'total' => 3])
            ->where('sentence.position', 2)
            ->where('sentence.words', ['— Salom,', 'mushuk!', '— dedi', 'Ali.']),
        );
});

test('a finished reading is not resumed; opening the story starts a new one', function () {
    $finished = Reading::factory()->for($this->child)->for($this->story)->finished()->create(['next_sentence_position' => 3]);
    $siblingsReading = Reading::factory()->for(Child::factory()->for($this->child->parent, 'parent'))->for($this->story)->create();

    actingAsChild($this->child)->post(route('readings.store', $this->story));

    $new = Reading::query()->whereKeyNot([$finished->id, $siblingsReading->id])->sole();
    expect($new->child_id)->toBe($this->child->id)
        ->and($new->next_sentence_position)->toBe(0);
});

test("another child's reading cannot be opened", function () {
    $siblingsReading = Reading::factory()->for(Child::factory()->for($this->child->parent, 'parent'))->for($this->story)->create();

    actingAsChild($this->child)->get(route('readings.show', $siblingsReading))->assertNotFound();
    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $siblingsReading), sentenceClip(0))
        ->assertNotFound();
});

test('reading needs an active child', function () {
    $this->post(route('readings.store', $this->story))->assertRedirect(route('login'));
    $this->actingAs($this->child->parent)->post(route('readings.store', $this->story))->assertRedirect(route('picker'));
});

test('a sentence read correctly marks every word correct and moves on to the next sentence', function () {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create();
    speech()->hears('Ali uyga keldi');

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $reading), sentenceClip(0, 2300))
        ->assertOk()
        ->assertExactJson([
            'status' => 'scored',
            'marks' => [
                ['word' => 'Ali', 'correct' => true],
                ['word' => 'uyga', 'correct' => true],
                ['word' => 'keldi.', 'correct' => true],
            ],
            'progress' => ['read' => 1, 'total' => 3],
            'nextSentence' => ['position' => 1, 'words' => ['Mushuk', 'o‘yinchoq', 'bilan', "o'ynadi!"]],
        ]);

    $reading->refresh();
    expect($reading->next_sentence_position)->toBe(1)
        ->and($reading->reading_time_ms)->toBe(2300)
        ->and($reading->wordResults->pluck('outcome')->all())->toBe(array_fill(0, 3, WordOutcome::CorrectFirstTry))
        ->and($reading->wordResults->pluck('word.text')->all())->toBe(['Ali', 'uyga', 'keldi.']);
});

test('a misread word is marked wrong and gets no first-try result', function () {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create();
    speech()->hears('Ali uyda keldi');

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $reading), sentenceClip(0))
        ->assertOk()
        ->assertJsonPath('marks', [
            ['word' => 'Ali', 'correct' => true],
            ['word' => 'uyga', 'correct' => false],
            ['word' => 'keldi.', 'correct' => true],
        ]);

    expect($reading->wordResults()->with('word')->get()->pluck('word.text')->all())->toBe(['Ali', 'keldi.']);
});

test('words are matched in order, ignoring capitals, punctuation and apostrophe variants', function (string $heard, array $correct) {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create(['next_sentence_position' => 1]);
    speech()->hears($heard);

    $response = actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $reading), sentenceClip(1))
        ->assertOk();

    expect(array_column($response->json('marks'), 'correct'))->toBe($correct);
})->with([
    'read exactly as written' => ["Mushuk o‘yinchoq bilan o'ynadi!", [true, true, true, true]],
    'capitals and punctuation' => ['MUSHUK, o‘yinchoq... bilan O‘YNADI', [true, true, true, true]],
    'apostrophe variants' => ['mushuk oʻyinchoq bilan o`ynadi', [true, true, true, true]],
    'o without its apostrophe is a different word' => ['mushuk oyinchoq bilan oynadi', [true, false, true, false]],
    'a skipped word' => ['mushuk bilan o‘ynadi', [true, false, true, true]],
    'extra words' => ['mushuk mushuk katta o‘yinchoq bilan ham o‘ynadi', [true, true, true, true]],
    'words out of order match only in order' => ['bilan mushuk o‘yinchoq o‘ynadi', [true, true, false, true]],
    'the last word missing' => ['mushuk o‘yinchoq bilan', [true, true, true, false]],
]);

test('a dash is not a word of its own when matching', function () {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create(['next_sentence_position' => 2]);
    speech()->hears('— Salom mushuk — dedi Ali');

    $response = actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $reading), sentenceClip(2))
        ->assertOk();

    expect(array_column($response->json('marks'), 'correct'))->toBe([true, true, true, true]);
});

test('when nothing is heard or the service fails, nothing is recorded and the sentence is read again', function (Closure $script, string $reason) {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create(['reading_time_ms' => 1000]);
    $script();

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $reading), sentenceClip(0, 2000))
        ->assertOk()
        ->assertExactJson(['status' => 'unheard', 'reason' => $reason]);

    $reading->refresh();
    expect($reading->next_sentence_position)->toBe(0)
        ->and($reading->reading_time_ms)->toBe(1000)
        ->and(WordResult::count())->toBe(0);
})->with([
    'nothing heard' => [fn () => speech()->hearsNothing(), 'nothing_heard'],
    'only noise' => [fn () => speech()->hears('... —'), 'nothing_heard'],
    'service failure' => [fn () => speech()->fails(), 'service_failed'],
]);

test('the clip goes to speech-to-text with the expected text and is never stored', function () {
    Storage::fake('local');
    Storage::fake('public');
    $reading = Reading::factory()->for($this->child)->for($this->story)->create();

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $reading), sentenceClip(0))
        ->assertOk();

    expect(speech()->requests())->toHaveCount(1)
        ->and(speech()->requests()[0])->toMatchArray([
            'audio' => 'fake-opus-bytes',
            'expectedText' => 'Ali uyga keldi.',
            'language' => 'uz',
        ])
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('reading time adds up over the sentences', function () {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create();

    actingAsChild($this->child)->postJson(route('readings.sentences.store', $reading), sentenceClip(0, 2000));
    actingAsChild($this->child)->postJson(route('readings.sentences.store', $reading), sentenceClip(1, 3500));

    expect($reading->refresh()->reading_time_ms)->toBe(5500);
});

test('after the last sentence there is no next sentence and the reading screen gives way to the result', function () {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create(['next_sentence_position' => 2]);

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $reading), sentenceClip(2))
        ->assertOk()
        ->assertJsonPath('progress', ['read' => 3, 'total' => 3])
        ->assertJsonPath('nextSentence', null);

    actingAsChild($this->child)
        ->get(route('readings.show', $reading))
        ->assertRedirect(route('readings.result', $reading));
});

test('a clip for a sentence other than the next one is refused', function () {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create(['next_sentence_position' => 1]);

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $reading), sentenceClip(0))
        ->assertConflict();

    expect(speech()->requests())->toBe([])
        ->and($reading->refresh()->next_sentence_position)->toBe(1);
});

test('a finished reading takes no more sentences', function () {
    $reading = Reading::factory()->for($this->child)->for($this->story)->finished()->create();

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $reading), sentenceClip(0))
        ->assertConflict();
});

test('a sentence needs a clip and its length', function () {
    $reading = Reading::factory()->for($this->child)->for($this->story)->create();

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $reading), ['sentence' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['audio', 'duration_ms']);
});
