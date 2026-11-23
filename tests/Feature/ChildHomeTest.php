<?php

use App\Models\Child;
use App\Models\Reading;
use App\Models\Story;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Finish a Reading for the Child at the given Tashkent wall-clock time,
 * stored in UTC like every timestamp the app writes.
 */
function finishedReadingAt(Child $child, string $tashkentTime): Reading
{
    $finishedAt = CarbonImmutable::parse($tashkentTime, 'Asia/Tashkent')->utc();

    return Reading::factory()->for($child)->finished()->create([
        'started_at' => $finishedAt->subMinutes(5),
        'finished_at' => $finishedAt,
    ]);
}

/**
 * Freeze the clock at the given Tashkent wall-clock time.
 */
function nowInTashkent(string $tashkentTime): void
{
    test()->travelTo(CarbonImmutable::parse($tashkentTime, 'Asia/Tashkent')->utc());
}

/**
 * Assert the Streak the active Child's home screen shows.
 */
function assertHomeStreak(Child $child, int $streak): void
{
    actingAsChild($child)
        ->get(route('child.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('child/home')->where('streak', $streak));
}

test('the streak counts consecutive tashkent days with a finished reading, up to today', function () {
    $child = Child::factory()->create();
    finishedReadingAt($child, '2026-03-02 09:00');
    finishedReadingAt($child, '2026-03-03 18:00');
    finishedReadingAt($child, '2026-03-04 08:00');
    finishedReadingAt($child, '2026-03-04 10:00');
    nowInTashkent('2026-03-04 20:00');

    assertHomeStreak($child, 3);
});

test('the streak counts back from yesterday while nothing is finished today yet', function () {
    $child = Child::factory()->create();
    finishedReadingAt($child, '2026-03-02 09:00');
    finishedReadingAt($child, '2026-03-03 18:00');
    nowInTashkent('2026-03-04 20:00');

    assertHomeStreak($child, 2);
});

test('the streak is zero when neither today nor yesterday has a finished reading', function () {
    $child = Child::factory()->create();
    finishedReadingAt($child, '2026-03-01 09:00');
    finishedReadingAt($child, '2026-03-02 18:00');
    nowInTashkent('2026-03-04 08:00');

    assertHomeStreak($child, 0);
});

test('a missed day breaks the streak', function () {
    $child = Child::factory()->create();
    finishedReadingAt($child, '2026-03-01 09:00');
    finishedReadingAt($child, '2026-03-03 09:00');
    finishedReadingAt($child, '2026-03-04 09:00');
    nowInTashkent('2026-03-04 20:00');

    assertHomeStreak($child, 2);
});

test('an unfinished reading does not count for the streak', function () {
    $child = Child::factory()->create();
    finishedReadingAt($child, '2026-03-03 09:00');
    Reading::factory()->for($child)->create(['started_at' => CarbonImmutable::parse('2026-03-04 09:00', 'Asia/Tashkent')->utc()]);
    nowInTashkent('2026-03-04 20:00');

    assertHomeStreak($child, 1);
});

test('the streak follows tashkent calendar days, not utc ones', function () {
    $child = Child::factory()->create();
    // 23:30 and 00:30 in Tashkent are 18:30 and 19:30 on the same UTC day.
    finishedReadingAt($child, '2026-03-02 23:30');
    finishedReadingAt($child, '2026-03-03 00:30');

    nowInTashkent('2026-03-03 00:40');
    assertHomeStreak($child, 2);

    nowInTashkent('2026-03-04 23:59');
    assertHomeStreak($child, 2);

    // Just past Tashkent midnight, while it is still 03-04 in UTC.
    nowInTashkent('2026-03-05 00:10');
    assertHomeStreak($child, 0);
});

test('the streak only counts the active child\'s readings', function () {
    $child = Child::factory()->create();
    $sibling = Child::factory()->for($child->parent, 'parent')->create();
    finishedReadingAt($sibling, '2026-03-04 09:00');
    nowInTashkent('2026-03-04 20:00');

    assertHomeStreak($child, 0);
});

test('the home screen shows the stars total: the best stars of each finished story', function () {
    $child = Child::factory()->create();
    $story = Story::factory()->create();
    Reading::factory()->for($child)->for($story)->finished()->create(['stars' => 1]);
    Reading::factory()->for($child)->for($story)->finished()->create(['stars' => 3]);
    Reading::factory()->for($child)->finished()->create(['stars' => 2]);
    Reading::factory()->for($child)->create();

    actingAsChild($child)
        ->get(route('child.home'))
        ->assertInertia(fn (Assert $page) => $page->where('starsTotal', 5));
});

test('the suggested story is the first story at the grade\'s level the child has not finished', function (int $grade, int $level) {
    $child = Child::factory()->create(['grade' => $grade]);
    foreach ([1, 2, 3] as $otherLevel) {
        Story::factory()->create(['title' => "A {$otherLevel}", 'level' => $otherLevel]);
    }
    $finished = Story::factory()->create(['title' => 'B finished', 'level' => $level]);
    Reading::factory()->for($child)->for($finished)->finished()->create();
    $suggested = Story::factory()->create(['title' => 'C next', 'level' => $level, 'minutes' => 4]);
    Story::factory()->create(['title' => 'D later', 'level' => $level]);
    Reading::factory()->for($child)->for(Story::firstWhere('title', "A {$level}"))->finished()->create();

    actingAsChild($child)
        ->get(route('child.home'))
        ->assertInertia(fn (Assert $page) => $page->where('suggestedStory', [
            'id' => $suggested->id,
            'title' => 'C next',
            'level' => $level,
            'minutes' => 4,
        ]));
})->with([
    'grade 1' => [1, 1],
    'grade 2' => [2, 2],
    'grade 3' => [3, 3],
    'grade 4' => [4, 3],
]);

test('the suggestion falls back to any unfinished story once the grade\'s level is all finished', function () {
    $child = Child::factory()->create(['grade' => 2]);
    $levelTwo = Story::factory()->create(['level' => 2]);
    Reading::factory()->for($child)->for($levelTwo)->finished()->create();
    $levelOne = Story::factory()->create(['level' => 1]);
    Reading::factory()->for($child)->for($levelOne)->finished()->create();
    $levelThree = Story::factory()->create(['level' => 3]);

    actingAsChild($child)
        ->get(route('child.home'))
        ->assertInertia(fn (Assert $page) => $page->where('suggestedStory.id', $levelThree->id));
});

test('there is no suggestion once every story is finished', function () {
    $child = Child::factory()->create();
    Reading::factory()->for($child)->finished()->create();

    actingAsChild($child)
        ->get(route('child.home'))
        ->assertInertia(fn (Assert $page) => $page->where('suggestedStory', null));
});

test('another child\'s finished reading does not affect the suggestion', function () {
    $child = Child::factory()->create(['grade' => 1]);
    $story = Story::factory()->create(['level' => 1]);
    Reading::factory()->for(Child::factory())->for($story)->finished()->create();

    actingAsChild($child)
        ->get(route('child.home'))
        ->assertInertia(fn (Assert $page) => $page->where('suggestedStory.id', $story->id));
});

test('an unfinished reading shows as a continue card with how far the child got', function () {
    $this->artisan('stories:load', ['--path' => base_path('tests/Fixtures/twenty-words')])->assertSuccessful();
    $child = Child::factory()->create();
    $story = Story::firstWhere('slug', 'yigirma-soz');
    $reading = Reading::factory()->for($child)->for($story)->create(['next_sentence_position' => 1]);

    actingAsChild($child)
        ->get(route('child.home'))
        ->assertInertia(fn (Assert $page) => $page->where('continueReading', [
            'id' => $reading->id,
            'story' => ['id' => $story->id, 'title' => "Yigirma so'z"],
            'progress' => ['read' => 1, 'total' => 2],
        ]));
});

test('the continue card shows the unfinished reading the child read most recently', function () {
    $child = Child::factory()->create();
    Reading::factory()->for($child)->create(['updated_at' => now()->subDays(2)]);
    $latest = Reading::factory()->for($child)->create(['updated_at' => now()->subHour()]);
    Reading::factory()->for($child)->create(['updated_at' => now()->subDay()]);
    Reading::factory()->for($child)->finished()->create(['updated_at' => now()]);

    actingAsChild($child)
        ->get(route('child.home'))
        ->assertInertia(fn (Assert $page) => $page->where('continueReading.id', $latest->id));
});

test('there is no continue card without an unfinished reading of the active child', function () {
    $child = Child::factory()->create();
    Reading::factory()->for($child)->finished()->create();
    Reading::factory()->for(Child::factory()->for($child->parent, 'parent'))->create();

    actingAsChild($child)
        ->get(route('child.home'))
        ->assertInertia(fn (Assert $page) => $page->where('continueReading', null));
});
