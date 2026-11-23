<?php

use App\Enums\WordOutcome;
use App\Models\Child;
use App\Models\Reading;
use App\Models\Story;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->artisan('stories:load', ['--path' => base_path('tests/Fixtures/stories')])->assertSuccessful();
    $this->story = Story::firstWhere('slug', 'ali-va-mushuk');
    $this->child = Child::factory()->create();
    $this->reading = Reading::factory()->for($this->child)->for($this->story)->create();
});

/**
 * Get the outcome recorded for each word of the Reading, keyed by the word as displayed.
 *
 * @return array<string, WordOutcome>
 */
function outcomes(Reading $reading): array
{
    return $reading->wordResults()->with('word')->get()
        ->mapWithKeys(fn ($result) => [$result->word->text => $result->outcome])
        ->all();
}

test('a misread word gets a retry, and reading it right on the retry records it as correct on retry', function () {
    speech()->hears('Ali uyda keldi');

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0))
        ->assertOk()
        ->assertJsonPath('pendingWord', [
            'stage' => 'retry',
            'sentence' => 0,
            'position' => 1,
            'text' => 'uyga',
            'syllables' => ['uy', 'ga'],
            'clips' => ['syllables' => null, 'word' => null],
        ]);

    speech()->hears('uyga');

    actingAsChild($this->child)
        ->postJson(route('readings.retries.store', $this->reading), wordClip(0, 1))
        ->assertOk()
        ->assertExactJson([
            'status' => 'scored',
            'correct' => true,
            'pendingWord' => null,
            'progress' => ['read' => 1, 'total' => 3],
            'nextSentence' => ['position' => 1, 'words' => ['Mushuk', 'o‘yinchoq', 'bilan', "o'ynadi!"]],
        ]);

    expect(outcomes($this->reading))->toBe([
        'Ali' => WordOutcome::CorrectFirstTry,
        'uyga' => WordOutcome::CorrectOnRetry,
        'keldi.' => WordOutcome::CorrectFirstTry,
    ])->and(speech()->requests()[1]['expectedText'])->toBe('uyga');
});

test('a word misread on its retry too opens Syllable help with its syllables and is recorded as needing Syllable help', function () {
    speech()->hears('Ali uyda keldi', 'uyda');

    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0));

    actingAsChild($this->child)
        ->postJson(route('readings.retries.store', $this->reading), wordClip(0, 1))
        ->assertOk()
        ->assertJsonPath('status', 'scored')
        ->assertJsonPath('correct', false)
        ->assertJsonPath('pendingWord', [
            'stage' => 'syllable_help',
            'sentence' => 0,
            'position' => 1,
            'text' => 'uyga',
            'syllables' => ['uy', 'ga'],
            'clips' => ['syllables' => null, 'word' => null],
        ]);

    expect(outcomes($this->reading)['uyga'])->toBe(WordOutcome::NeededSyllableHelp);

    actingAsChild($this->child)
        ->get(route('readings.show', $this->reading))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendingWord.stage', 'syllable_help')
            ->where('pendingWord.syllables', ['uy', 'ga']),
        );
});

test('Syllable help gets the URLs of the syllables clip and the whole-word clip once the audio is generated', function () {
    $this->artisan('audio:generate')->assertSuccessful();
    $clips = [
        'syllables' => '/storage/audio/syllables/o_-yin-choq.wav',
        'word' => '/storage/audio/words/o_yinchoq.wav',
    ];
    $this->reading->update(['next_sentence_position' => 1]);
    speech()->hears("Mushuk oyinchoq bilan o'ynadi", 'oyinchoq');

    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(1));

    actingAsChild($this->child)
        ->postJson(route('readings.retries.store', $this->reading), wordClip(1, 1))
        ->assertOk()
        ->assertJsonPath('pendingWord.stage', 'syllable_help')
        ->assertJsonPath('pendingWord.text', 'o‘yinchoq')
        ->assertJsonPath('pendingWord.clips', $clips);

    actingAsChild($this->child)
        ->get(route('readings.show', $this->reading))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendingWord.stage', 'syllable_help')
            ->where('pendingWord.clips', $clips),
        );
});

test('after Syllable help the Child reads the word once more, unscored, and the Story continues', function () {
    speech()->hears('Ali uyda keldi', 'uyda');
    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0, 2000));
    actingAsChild($this->child)->postJson(route('readings.retries.store', $this->reading), wordClip(0, 1, 700));

    actingAsChild($this->child)
        ->postJson(route('readings.syllable-help.store', $this->reading), ['sentence' => 0, 'word' => 1])
        ->assertOk()
        ->assertExactJson([
            'status' => 'done',
            'pendingWord' => null,
            'progress' => ['read' => 1, 'total' => 3],
            'nextSentence' => ['position' => 1, 'words' => ['Mushuk', 'o‘yinchoq', 'bilan', "o'ynadi!"]],
        ]);

    $this->reading->refresh();
    expect(speech()->requests())->toHaveCount(2)
        ->and(outcomes($this->reading)['uyga'])->toBe(WordOutcome::NeededSyllableHelp)
        ->and($this->reading->reading_time_ms)->toBe(2700);

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $this->reading), sentenceClip(1))
        ->assertOk()
        ->assertJsonPath('pendingWord', null);
});

test('misread words are retried one at a time, in order, before the next sentence is offered', function () {
    $this->reading->update(['next_sentence_position' => 1]);
    speech()->hears('mushuk oyinchoq bilan oynadi');

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $this->reading), sentenceClip(1))
        ->assertJsonPath('pendingWord.text', 'o‘yinchoq')
        ->assertJsonPath('pendingWord.position', 1);

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $this->reading), sentenceClip(2))
        ->assertConflict();
    actingAsChild($this->child)
        ->postJson(route('readings.retries.store', $this->reading), wordClip(1, 3))
        ->assertConflict();

    actingAsChild($this->child)
        ->postJson(route('readings.retries.store', $this->reading), wordClip(1, 1))
        ->assertJsonPath('correct', true)
        ->assertJsonPath('pendingWord.stage', 'retry')
        ->assertJsonPath('pendingWord.text', "o'ynadi!")
        ->assertJsonPath('pendingWord.syllables', ["o'y", 'na', 'di']);

    actingAsChild($this->child)
        ->get(route('readings.show', $this->reading))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendingWord.stage', 'retry')
            ->where('pendingWord.position', 3)
            ->where('sentence.position', 2),
        );

    actingAsChild($this->child)
        ->postJson(route('readings.retries.store', $this->reading), wordClip(1, 3))
        ->assertJsonPath('correct', true)
        ->assertJsonPath('pendingWord', null)
        ->assertJsonPath('nextSentence.position', 2);

    expect(speech()->requests()[2]['expectedText'])->toBe("o'ynadi!");

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $this->reading), sentenceClip(2))
        ->assertOk();
});

test('when nothing is heard or the service fails on a retry, nothing is recorded and the word is tried again', function (Closure $script, string $reason) {
    speech()->hears('Ali uyda keldi');
    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0, 2000));
    $script();

    actingAsChild($this->child)
        ->postJson(route('readings.retries.store', $this->reading), wordClip(0, 1, 900))
        ->assertOk()
        ->assertExactJson(['status' => 'unheard', 'reason' => $reason]);

    $this->reading->refresh();
    expect($this->reading->reading_time_ms)->toBe(2000)
        ->and(outcomes($this->reading))->not->toHaveKey('uyga')
        ->and($this->reading->hasPendingRetries())->toBeTrue();

    actingAsChild($this->child)
        ->postJson(route('readings.retries.store', $this->reading), wordClip(0, 1))
        ->assertJsonPath('correct', true);
})->with([
    'nothing heard' => [fn () => speech()->hearsNothing(), 'nothing_heard'],
    'service failure' => [fn () => speech()->fails(), 'service_failed'],
]);

test('a misread last sentence never leaves the Story stuck', function () {
    $this->reading->update(['next_sentence_position' => 2]);
    speech()->hears('Salom mushuk dedi', 'Olim');

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $this->reading), sentenceClip(2))
        ->assertJsonPath('nextSentence', null)
        ->assertJsonPath('pendingWord.text', 'Ali.');

    actingAsChild($this->child)
        ->postJson(route('readings.retries.store', $this->reading), wordClip(2, 3))
        ->assertJsonPath('pendingWord.stage', 'syllable_help');

    actingAsChild($this->child)
        ->postJson(route('readings.syllable-help.store', $this->reading), ['sentence' => 2, 'word' => 3])
        ->assertOk()
        ->assertJsonPath('pendingWord', null)
        ->assertJsonPath('nextSentence', null)
        ->assertJsonPath('progress', ['read' => 3, 'total' => 3]);

    expect($this->reading->refresh()->hasPendingRetries())->toBeFalse();
});

test('Syllable help can only be closed for the word it is open for', function () {
    speech()->hears('Ali uyda keldi');
    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0));

    actingAsChild($this->child)
        ->postJson(route('readings.syllable-help.store', $this->reading), ['sentence' => 0, 'word' => 1])
        ->assertConflict();

    speech()->hears('uyda');
    actingAsChild($this->child)->postJson(route('readings.retries.store', $this->reading), wordClip(0, 1));

    actingAsChild($this->child)
        ->postJson(route('readings.retries.store', $this->reading), wordClip(0, 1))
        ->assertConflict();
    actingAsChild($this->child)
        ->postJson(route('readings.syllable-help.store', $this->reading), ['sentence' => 0, 'word' => 2])
        ->assertConflict();
    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $this->reading), sentenceClip(1))
        ->assertConflict();
});

test("another child's retries and Syllable help are out of reach", function () {
    $siblingsReading = Reading::factory()->for(Child::factory()->for($this->child->parent, 'parent'))->for($this->story)->create();

    actingAsChild($this->child)
        ->postJson(route('readings.retries.store', $siblingsReading), wordClip(0, 1))
        ->assertNotFound();
    actingAsChild($this->child)
        ->postJson(route('readings.syllable-help.store', $siblingsReading), ['sentence' => 0, 'word' => 1])
        ->assertNotFound();
});

test('a retry needs the word, a clip and its length', function () {
    actingAsChild($this->child)
        ->postJson(route('readings.retries.store', $this->reading), ['sentence' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['word', 'audio', 'duration_ms']);
});

test('a Reading whose last sentence had a misread word finishes only once its retry is done', function () {
    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0, 2000));
    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(1, 2000));
    speech()->hears('Salom mushuk dedi Olim');

    actingAsChild($this->child)
        ->postJson(route('readings.sentences.store', $this->reading), sentenceClip(2, 2000))
        ->assertJsonPath('pendingWord.text', 'Ali.');

    expect($this->reading->refresh()->finished_at)->toBeNull();

    actingAsChild($this->child)
        ->postJson(route('readings.retries.store', $this->reading), wordClip(2, 3, 1000))
        ->assertJsonPath('correct', true)
        ->assertJsonPath('pendingWord', null)
        ->assertJsonPath('nextSentence', null);

    expect($this->reading->refresh()->finished_at)->not->toBeNull();

    actingAsChild($this->child)
        ->get(route('readings.show', $this->reading))
        ->assertRedirect(route('readings.result', $this->reading));

    // 10 of the 11 words right on the first try, in 7 seconds of reading including the retry.
    actingAsChild($this->child)
        ->get(route('readings.result', $this->reading))
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.accuracy', 91)
            ->where('result.stars', 2)
            ->where('result.readingSpeed', 86)
            ->where('result.wordsToPractise', ['Ali']),
        );
});

test('a Reading ending in Syllable help finishes once Syllable help is closed, and the helped word does not count as correct', function () {
    $this->reading->update(['next_sentence_position' => 2]);
    speech()->hears('Salom mushuk dedi Olim', 'Olim');
    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(2, 3000));
    actingAsChild($this->child)->postJson(route('readings.retries.store', $this->reading), wordClip(2, 3, 1000));

    expect($this->reading->refresh()->finished_at)->toBeNull();

    actingAsChild($this->child)
        ->postJson(route('readings.syllable-help.store', $this->reading), ['sentence' => 2, 'word' => 3])
        ->assertOk();

    expect($this->reading->refresh()->finished_at)->not->toBeNull();

    // Only the 3 words of the last sentence read right count, out of the Story's 11.
    actingAsChild($this->child)
        ->get(route('readings.result', $this->reading))
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.accuracy', 27)
            ->where('result.stars', 1)
            ->where('result.wordsToPractise', ['Ali', 'uyga', 'keldi', 'Mushuk', 'o‘yinchoq', 'bilan', "o'ynadi"]),
        );
});
