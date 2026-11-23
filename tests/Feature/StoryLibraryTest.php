<?php

use App\Models\Child;
use App\Models\Reading;
use App\Models\Story;
use Inertia\Testing\AssertableInertia as Assert;

test('the library lists every story with its title, level and minutes, easiest first', function () {
    $child = Child::factory()->create();
    $qush = Story::factory()->create(['slug' => 'kichik-qush', 'title' => 'Kichik qush', 'level' => 2, 'minutes' => 3]);
    $ali = Story::factory()->create(['slug' => 'ali-va-mushuk', 'title' => 'Ali va mushuk', 'level' => 1, 'minutes' => 2]);
    $dengiz = Story::factory()->create(['slug' => 'dengiz-boyida', 'title' => "Dengiz bo'yida", 'level' => 3, 'minutes' => 4]);

    actingAsChild($child)
        ->get(route('stories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('child/stories')
            ->where('level', null)
            ->where('stories', [
                ['id' => $ali->id, 'slug' => 'ali-va-mushuk', 'title' => 'Ali va mushuk', 'level' => 1, 'minutes' => 2, 'isNew' => true],
                ['id' => $qush->id, 'slug' => 'kichik-qush', 'title' => 'Kichik qush', 'level' => 2, 'minutes' => 3, 'isNew' => true],
                ['id' => $dengiz->id, 'slug' => 'dengiz-boyida', 'title' => "Dengiz bo'yida", 'level' => 3, 'minutes' => 4, 'isNew' => true],
            ]),
        );
});

test('a story is new until the active child has finished a reading of it', function () {
    $child = Child::factory()->create();
    $finished = Story::factory()->create(['level' => 1]);
    $unfinished = Story::factory()->create(['level' => 2]);
    $finishedBySibling = Story::factory()->create(['level' => 3]);
    $sibling = Child::factory()->for($child->parent, 'parent')->create();

    Reading::factory()->for($child)->for($finished)->finished()->create();
    Reading::factory()->for($child)->for($unfinished)->create();
    Reading::factory()->for($sibling)->for($finishedBySibling)->finished()->create();

    actingAsChild($child)
        ->get(route('stories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stories.0.isNew', false)
            ->where('stories.1.isNew', true)
            ->where('stories.2.isNew', true),
        );
});

test('the library can be filtered by level', function () {
    $child = Child::factory()->create();
    Story::factory()->create(['title' => 'Ali va mushuk', 'level' => 1]);
    Story::factory()->create(['title' => 'Kichik qush', 'level' => 2]);
    Story::factory()->create(['title' => 'Yomg\'ir', 'level' => 2]);

    actingAsChild($child)
        ->get(route('stories.index', ['level' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('level', 2)
            ->has('stories', 2)
            ->where('stories.0.title', 'Kichik qush')
            ->where('stories.1.title', "Yomg'ir"),
        );
});

test('an unknown level shows every story', function () {
    $child = Child::factory()->create();
    Story::factory()->count(2)->create();

    actingAsChild($child)
        ->get(route('stories.index', ['level' => 7]))
        ->assertInertia(fn (Assert $page) => $page->where('level', null)->has('stories', 2));
});

test('the library needs an active child', function () {
    $child = Child::factory()->create();

    $this->get(route('stories.index'))->assertRedirect(route('login'));
    $this->actingAs($child->parent)->get(route('stories.index'))->assertRedirect(route('picker'));
});
