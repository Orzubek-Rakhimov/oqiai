<?php

use App\Models\Child;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a new parent is taken to create their first child right after sign-up', function () {
    $this->post(route('register.store'), [
        'name' => 'Dilnoza',
        'phone' => '+998901234567',
        'password' => 'password',
    ])->assertRedirect(route('children.create', absolute: false));

    $this->get(route('children.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/children/create')
            ->where('isFirstChild', true)
            ->has('avatars', 6),
        );
});

test('a parent creates a child with a name, a grade and an avatar', function () {
    $parent = User::factory()->create();

    $response = $this->actingAs($parent)
        ->post(route('children.store'), [
            'name' => 'Ali',
            'grade' => 2,
            'avatar' => 'fox',
        ])
        ->assertSessionHasNoErrors();

    $child = $parent->children()->sole();
    $response->assertRedirect(route('children.microphone', $child, absolute: false));
    expect($child->name)->toBe('Ali')
        ->and($child->grade)->toBe(2)
        ->and($child->avatar->value)->toBe('fox');
});

test('a child profile is validated with Uzbek messages', function (array $input, array $errors) {
    $parent = User::factory()->create();

    $this->actingAs($parent)
        ->post(route('children.store'), [
            'name' => 'Ali',
            'grade' => 2,
            'avatar' => 'fox',
            ...$input,
        ])
        ->assertSessionHasErrors($errors);

    expect($parent->children()->count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], ['name' => "Ism maydonini to'ldiring."]],
    'name too long' => [['name' => str_repeat('a', 51)], ['name' => 'Ism 50 belgidan oshmasligi kerak.']],
    'missing grade' => [['grade' => ''], ['grade' => "Sinf maydonini to'ldiring."]],
    'grade below 1' => [['grade' => 0], ['grade' => "Sinf 1 dan 4 gacha bo'lishi kerak."]],
    'grade above 4' => [['grade' => 5], ['grade' => "Sinf 1 dan 4 gacha bo'lishi kerak."]],
    'missing avatar' => [['avatar' => ''], ['avatar' => "Rasm maydonini to'ldiring."]],
    'unknown avatar' => [['avatar' => 'dragon'], ['avatar' => "Tanlangan rasm noto'g'ri."]],
]);

test('a child profile stores nothing beyond name, grade and avatar', function () {
    $parent = User::factory()->create();

    $this->actingAs($parent)->post(route('children.store'), [
        'name' => 'Ali',
        'grade' => 1,
        'avatar' => 'owl',
        'birthday' => '2019-01-01',
        'user_id' => User::factory()->create()->id,
    ])->assertSessionHasNoErrors();

    expect($parent->children()->sole()->getAttributes())
        ->toHaveKeys(['id', 'user_id', 'name', 'grade', 'avatar', 'microphone_checked_at', 'created_at', 'updated_at'])
        ->toHaveCount(8);
});

test('a parent with children adds another and returns to the parent area', function () {
    $parent = User::factory()->has(Child::factory())->create();

    $this->actingAs($parent)
        ->get(route('children.create'))
        ->assertInertia(fn (Assert $page) => $page->where('isFirstChild', false));

    $this->actingAs($parent)
        ->post(route('children.store'), ['name' => 'Madina', 'grade' => 4, 'avatar' => 'cat'])
        ->assertRedirect(route('parent.home', absolute: false));

    expect($parent->children()->count())->toBe(2);
});

test('the parent area lists only the parent\'s own children', function () {
    $parent = User::factory()->create();
    $ali = Child::factory()->for($parent, 'parent')->create(['name' => 'Ali', 'grade' => 2, 'avatar' => 'fox']);
    Child::factory()->create(['name' => 'Begona']);

    $this->actingAs($parent)
        ->get(route('parent.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/home')
            ->has('childProfiles', 1)
            ->where('childProfiles.0', [
                'id' => $ali->id,
                'name' => 'Ali',
                'grade' => 2,
                'avatar' => 'fox',
            ]),
        );
});

test('guests cannot reach the parent area or child profiles', function () {
    $child = Child::factory()->create();

    $this->get(route('parent.home'))->assertRedirect(route('login'));
    $this->get(route('children.create'))->assertRedirect(route('login'));
    $this->post(route('children.store'))->assertRedirect(route('login'));
    $this->get(route('children.edit', $child))->assertRedirect(route('login'));
    $this->patch(route('children.update', $child))->assertRedirect(route('login'));
    $this->delete(route('children.destroy', $child))->assertRedirect(route('login'));
});

test('a parent edits a child\'s name, grade and avatar', function () {
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent, 'parent')->create(['name' => 'Ali', 'grade' => 1, 'avatar' => 'fox']);

    $this->actingAs($parent)
        ->get(route('children.edit', $child))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/children/edit')
            ->where('child.name', 'Ali')
            ->where('child.grade', 1)
            ->where('child.avatar', 'fox')
            ->has('avatars', 6),
        );

    $this->actingAs($parent)
        ->patch(route('children.update', $child), ['name' => 'Alisher', 'grade' => 2, 'avatar' => 'lion'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('parent.home', absolute: false));

    $child->refresh();
    expect($child->name)->toBe('Alisher')
        ->and($child->grade)->toBe(2)
        ->and($child->avatar->value)->toBe('lion');
});

test('editing a child is validated with Uzbek messages', function () {
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent, 'parent')->create(['grade' => 1]);

    $this->actingAs($parent)
        ->patch(route('children.update', $child), ['name' => 'Ali', 'grade' => 7, 'avatar' => 'fox'])
        ->assertSessionHasErrors(['grade' => "Sinf 1 dan 4 gacha bo'lishi kerak."]);

    expect($child->refresh()->grade)->toBe(1);
});

test('a parent deletes a child', function () {
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent, 'parent')->create();

    $this->actingAs($parent)
        ->delete(route('children.destroy', $child))
        ->assertRedirect(route('parent.home', absolute: false));

    expect($parent->children()->count())->toBe(0);
});

test('a parent can never see, edit or delete another parent\'s child', function () {
    $parent = User::factory()->create();
    $otherChild = Child::factory()->create(['name' => 'Begona', 'grade' => 3]);

    $this->actingAs($parent)->get(route('children.edit', $otherChild))->assertNotFound();
    $this->actingAs($parent)
        ->patch(route('children.update', $otherChild), ['name' => 'Ali', 'grade' => 1, 'avatar' => 'fox'])
        ->assertNotFound();
    $this->actingAs($parent)->delete(route('children.destroy', $otherChild))->assertNotFound();

    $otherChild->refresh();
    expect($otherChild->name)->toBe('Begona')
        ->and($otherChild->grade)->toBe(3);
});
