<?php

use App\Enums\WordOutcome;
use App\Models\Child;
use App\Models\Reading;
use App\Models\Story;
use App\Models\User;
use App\Models\Word;
use App\Models\WordResult;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Turn a Tashkent wall-clock time into the UTC moment the app stores.
 */
function tashkent(string $tashkentTime): CarbonImmutable
{
    return CarbonImmutable::parse($tashkentTime, 'Asia/Tashkent')->utc();
}

/**
 * Finish a Reading for the Child at the given Tashkent wall-clock time.
 *
 * @param  array<string, mixed>  $attributes
 */
function readingFinishedInTashkent(Child $child, string $tashkentTime, array $attributes = []): Reading
{
    $finishedAt = tashkent($tashkentTime);

    return Reading::factory()->for($child)->finished()->create([
        'started_at' => $finishedAt->subMinutes(5),
        'finished_at' => $finishedAt,
        ...$attributes,
    ]);
}

/**
 * Record a word result in the Reading for the nth occurrence (from 0) of a
 * normalised word in the Reading's Story, at the given UTC moment.
 */
function wordResultFor(Reading $reading, string $normalized, WordOutcome $outcome, CarbonImmutable $recordedAt, int $occurrence = 0): WordResult
{
    /** @var Word $word */
    $word = $reading->story->words()->where('normalized', $normalized)->orderBy('words.id')->get()[$occurrence];

    return WordResult::factory()->for($reading)->for($word)->create([
        'outcome' => $outcome,
        'recorded_at' => $recordedAt,
    ]);
}

/**
 * Open the Parent screen as the Child's Parent, showing that Child.
 */
function parentScreenFor(Child $child): mixed
{
    return test()->actingAs($child->parent)->get(route('parent.home', ['child' => $child->id]));
}

test('days practised this week are the distinct tashkent days since monday with a finished reading', function () {
    $child = Child::factory()->create();
    readingFinishedInTashkent($child, '2026-03-01 23:30'); // Sunday of last week
    readingFinishedInTashkent($child, '2026-03-02 00:10'); // Monday
    readingFinishedInTashkent($child, '2026-03-02 18:00'); // Monday again
    readingFinishedInTashkent($child, '2026-03-04 04:30'); // Wednesday in Tashkent, still Tuesday in UTC
    Reading::factory()->for($child)->create(['started_at' => tashkent('2026-03-05 09:00')]);
    test()->travelTo(tashkent('2026-03-08 23:00')); // Sunday

    parentScreenFor($child)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/home')
            ->where('selectedChildId', $child->id)
            ->where('progress.daysPractised', 2),
        );
});

test('stories finished this week count each story once, from monday 00:00 in tashkent', function () {
    $child = Child::factory()->create();
    [$ali, $qush, $dengiz] = Story::factory()->count(3)->create();
    readingFinishedInTashkent($child, '2026-03-01 23:59', ['story_id' => $dengiz->id]); // last week
    readingFinishedInTashkent($child, '2026-03-02 00:00', ['story_id' => $ali->id]);
    readingFinishedInTashkent($child, '2026-03-03 10:00', ['story_id' => $ali->id]); // a re-read
    readingFinishedInTashkent($child, '2026-03-04 10:00', ['story_id' => $qush->id]);
    Reading::factory()->for($child)->for($dengiz)->create(['started_at' => tashkent('2026-03-04 11:00')]);
    test()->travelTo(tashkent('2026-03-04 12:00'));

    parentScreenFor($child)->assertInertia(fn (Assert $page) => $page
        ->where('progress.storiesFinished', 2)
        ->where('progress.daysPractised', 3),
    );
});

test('reading speed is the average of finished readings for this week and each of the three before, oldest first', function () {
    $child = Child::factory()->create();
    readingFinishedInTashkent($child, '2026-02-01 12:00', ['reading_speed' => 99.0]); // four weeks ago: not shown
    readingFinishedInTashkent($child, '2026-02-09 00:05', ['reading_speed' => 20.0]); // week of 9 February
    readingFinishedInTashkent($child, '2026-02-15 23:55', ['reading_speed' => 25.0]); // same week, Sunday night
    // week of 16 February: nothing finished
    readingFinishedInTashkent($child, '2026-02-23 09:00', ['reading_speed' => 31.0]);
    readingFinishedInTashkent($child, '2026-03-02 08:00', ['reading_speed' => 40.0]); // this week
    readingFinishedInTashkent($child, '2026-03-03 08:00', ['reading_speed' => 35.0]);
    Reading::factory()->for($child)->create(['started_at' => tashkent('2026-03-03 09:00')]);
    test()->travelTo(tashkent('2026-03-04 12:00'));

    parentScreenFor($child)->assertInertia(fn (Assert $page) => $page
        ->where('progress.readingSpeedByWeek', [
            ['weekStart' => '2026-02-09', 'readingSpeed' => 23],
            ['weekStart' => '2026-02-16', 'readingSpeed' => null],
            ['weekStart' => '2026-02-23', 'readingSpeed' => 31],
            ['weekStart' => '2026-03-02', 'readingSpeed' => 38],
        ]),
    );
});

test('hard words are the words needing a retry or syllable help in the last 7 days, grouped and ranked, top 5', function () {
    $this->artisan('stories:load', ['--path' => base_path('tests/Fixtures/stories')])->assertSuccessful();
    test()->travelTo(tashkent('2026-03-10 12:00'));
    $story = Story::query()->where('slug', 'ali-va-mushuk')->firstOrFail();
    $child = Child::factory()->create();
    $first = Reading::factory()->for($child)->for($story)->finished()->create();
    $second = Reading::factory()->for($child)->for($story)->create();
    $now = now();

    wordResultFor($first, 'mushuk', WordOutcome::CorrectOnRetry, $now->subDays(2));
    wordResultFor($first, 'mushuk', WordOutcome::NeededSyllableHelp, $now->subDays(2), occurrence: 1);
    wordResultFor($second, 'mushuk', WordOutcome::CorrectOnRetry, $now->subHour());
    wordResultFor($first, 'ali', WordOutcome::NeededSyllableHelp, $now->subDays(3));
    wordResultFor($second, 'ali', WordOutcome::CorrectOnRetry, $now->subHour());
    wordResultFor($first, 'keldi', WordOutcome::CorrectOnRetry, $now->subDays(3));
    wordResultFor($second, 'keldi', WordOutcome::CorrectOnRetry, $now->subDays(7)->addMinute()); // just inside the 7 days
    wordResultFor($first, 'uyga', WordOutcome::CorrectOnRetry, $now->subDay());
    wordResultFor($first, 'bilan', WordOutcome::NeededSyllableHelp, $now->subDay());
    wordResultFor($first, 'dedi', WordOutcome::CorrectOnRetry, $now->subDay());
    wordResultFor($first, "o'ynadi", WordOutcome::CorrectFirstTry, $now->subDay()); // read right the first time
    wordResultFor($second, "o'ynadi", WordOutcome::CorrectFirstTry, $now->subDay());
    wordResultFor($first, 'salom', WordOutcome::NeededSyllableHelp, $now->subDays(7)->subMinute()); // too long ago
    wordResultFor($second, 'salom', WordOutcome::NeededSyllableHelp, $now->subDays(8));
    $sibling = Child::factory()->for($child->parent, 'parent')->create();
    $siblingReading = Reading::factory()->for($sibling)->for($story)->create();
    wordResultFor($siblingReading, "o'yinchoq", WordOutcome::NeededSyllableHelp, $now->subHour());
    wordResultFor($siblingReading, 'salom', WordOutcome::NeededSyllableHelp, $now->subHour());

    parentScreenFor($child)->assertInertia(fn (Assert $page) => $page
        ->where('progress.hardWords', [
            ['word' => 'mushuk', 'count' => 3],
            ['word' => 'ali', 'count' => 2],
            ['word' => 'keldi', 'count' => 2],
            ['word' => 'bilan', 'count' => 1],
            ['word' => 'dedi', 'count' => 1],
        ]),
    );
});

test('the streak and stars total match the child home', function () {
    $child = Child::factory()->create();
    [$ali, $qush] = Story::factory()->count(2)->create();
    readingFinishedInTashkent($child, '2026-03-02 23:30', ['story_id' => $ali->id, 'stars' => 1]);
    readingFinishedInTashkent($child, '2026-03-03 00:30', ['story_id' => $ali->id, 'stars' => 3]); // 19:30 UTC on 2 March
    readingFinishedInTashkent($child, '2026-03-04 07:00', ['story_id' => $qush->id, 'stars' => 2]);
    test()->travelTo(tashkent('2026-03-05 21:00')); // nothing finished today yet

    parentScreenFor($child)->assertInertia(fn (Assert $page) => $page
        ->where('progress.streak', 3)
        ->where('progress.starsTotal', 5),
    );

    actingAsChild($child)->get(route('child.home'))->assertInertia(fn (Assert $page) => $page
        ->where('streak', 3)
        ->where('starsTotal', 5),
    );
});

test('the parent screen shows the first child until the parent switches to another', function () {
    $parent = User::factory()->create();
    $ali = Child::factory()->for($parent, 'parent')->create(['name' => 'Ali']);
    $madina = Child::factory()->for($parent, 'parent')->create(['name' => 'Madina']);
    readingFinishedInTashkent($madina, '2026-03-02 10:00');
    test()->travelTo(tashkent('2026-03-02 12:00'));

    $this->actingAs($parent)->get(route('parent.home'))->assertInertia(fn (Assert $page) => $page
        ->where('selectedChildId', $ali->id)
        ->where('progress.hasRead', false)
        ->where('progress.daysPractised', 0),
    );

    $this->actingAs($parent)->get(route('parent.home', ['child' => $madina->id]))->assertInertia(fn (Assert $page) => $page
        ->has('childProfiles', 2)
        ->where('selectedChildId', $madina->id)
        ->where('progress.hasRead', true)
        ->where('progress.daysPractised', 1),
    );
});

test('a child who has not read yet gets the empty state with zero figures', function () {
    $child = Child::factory()->create();

    parentScreenFor($child)->assertInertia(fn (Assert $page) => $page
        ->where('progress.hasRead', false)
        ->where('progress.daysPractised', 0)
        ->where('progress.storiesFinished', 0)
        ->where('progress.readingSpeedByWeek.3.readingSpeed', null)
        ->where('progress.hardWords', [])
        ->where('progress.streak', 0)
        ->where('progress.starsTotal', 0),
    );
});

test('a parent without children has no progress to show', function () {
    $this->actingAs(User::factory()->create())->get(route('parent.home'))->assertInertia(fn (Assert $page) => $page
        ->where('childProfiles', [])
        ->where('selectedChildId', null)
        ->where('progress', null),
    );
});

test("another parent's child cannot be shown", function () {
    $child = Child::factory()->create();
    $stranger = Child::factory()->create();
    readingFinishedInTashkent($stranger, '2026-03-02 10:00');

    $this->actingAs($child->parent)->get(route('parent.home', ['child' => $stranger->id]))->assertNotFound();
});
