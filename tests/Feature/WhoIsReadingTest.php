<?php

use App\Models\Child;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('after login the parent lands on the picker', function () {
    User::factory()->has(Child::factory())->create(['phone' => '+998901234567']);

    $this->post(route('login.store'), [
        'phone' => '+998901234567',
        'password' => 'password',
    ])->assertRedirect(route('picker', absolute: false));
});

test('the picker shows each of the parent\'s children with avatar and name', function () {
    $parent = User::factory()->create();
    $ali = Child::factory()->for($parent, 'parent')->create(['name' => 'Ali', 'avatar' => 'fox']);
    $madina = Child::factory()->for($parent, 'parent')->create(['name' => 'Madina', 'avatar' => 'owl']);
    Child::factory()->create(['name' => 'Begona']);

    $this->actingAs($parent)
        ->get(route('picker'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('picker')
            ->has('childProfiles', 2)
            ->where('childProfiles.0', ['id' => $ali->id, 'name' => 'Ali', 'avatar' => 'fox'])
            ->where('childProfiles.1', ['id' => $madina->id, 'name' => 'Madina', 'avatar' => 'owl']),
        );
});

test('a parent without children is sent from the picker to create their first child', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('picker'))
        ->assertRedirect(route('children.create', absolute: false));
});

test('guests are sent to login from the picker and child screens', function () {
    $child = Child::factory()->create();

    $this->get(route('picker'))->assertRedirect(route('login'));
    $this->post(route('picker.choose', $child))->assertRedirect(route('login'));
    $this->get(route('child.home'))->assertRedirect(route('login'));
});

test('choosing a child makes it the active child and opens the child home', function () {
    $parent = User::factory()->create();
    $ali = Child::factory()->for($parent, 'parent')->create(['name' => 'Ali', 'grade' => 3, 'avatar' => 'bear']);

    $this->actingAs($parent)
        ->post(route('picker.choose', $ali))
        ->assertRedirect(route('child.home', absolute: false));

    $this->actingAs($parent)
        ->get(route('child.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('child/home')
            ->where('child', ['id' => $ali->id, 'name' => 'Ali', 'grade' => 3, 'avatar' => 'bear']),
        );
});

test('choosing another child switches the active child', function () {
    $parent = User::factory()->create();
    [$ali, $madina] = Child::factory()->for($parent, 'parent')->count(2)->create();

    $this->actingAs($parent)->post(route('picker.choose', $ali));
    $this->actingAs($parent)->post(route('picker.choose', $madina));

    $this->actingAs($parent)
        ->get(route('child.home'))
        ->assertInertia(fn (Assert $page) => $page->where('child.id', $madina->id));
});

test('the child home refuses access without an active child', function () {
    $parent = User::factory()->has(Child::factory())->create();

    $this->actingAs($parent)
        ->get(route('child.home'))
        ->assertRedirect(route('picker', absolute: false));
});

test('the child home refuses access once the active child is deleted', function () {
    $parent = User::factory()->create();
    [$ali] = Child::factory()->for($parent, 'parent')->count(2)->create();

    $this->actingAs($parent)->post(route('picker.choose', $ali));
    $this->actingAs($parent)->delete(route('children.destroy', $ali));

    $this->actingAs($parent)
        ->get(route('child.home'))
        ->assertRedirect(route('picker', absolute: false));
});

test('a parent can never choose another parent\'s child', function () {
    $parent = User::factory()->has(Child::factory())->create();
    $otherChild = Child::factory()->create();

    $this->actingAs($parent)
        ->post(route('picker.choose', $otherChild))
        ->assertNotFound();

    $this->actingAs($parent)
        ->get(route('child.home'))
        ->assertRedirect(route('picker', absolute: false));
});

test('an active child chosen by one parent is not active for another parent', function () {
    $parent = User::factory()->create();
    $ali = Child::factory()->for($parent, 'parent')->create();
    $otherParent = User::factory()->has(Child::factory())->create();

    $this->actingAs($parent)->post(route('picker.choose', $ali));

    $this->actingAs($otherParent)
        ->get(route('child.home'))
        ->assertRedirect(route('picker', absolute: false));
});

test('the parent button on the picker opens the parent area without a PIN', function () {
    $parent = User::factory()->has(Child::factory())->create();

    $this->actingAs($parent)
        ->get(route('parent.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('parent/home'));
});
