<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the profile settings page shows the parent\'s name and phone number', function () {
    $parent = User::factory()->create(['name' => 'Dilnoza', 'phone' => '+998901234567']);

    $this->actingAs($parent)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.user.name', 'Dilnoza')
            ->where('auth.user.phone', '+998901234567')
            ->missing('mustVerifyEmail'),
        );
});

test('guests are sent to the login page from profile settings', function () {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
});

test('a parent changes their name and phone number', function () {
    $parent = User::factory()->create(['phone' => '+998901234567']);

    $this->actingAs($parent)
        ->patch(route('profile.update'), [
            'name' => 'Dilnoza Karimova',
            'phone' => '93 765-43-21',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $parent->refresh();
    expect($parent->name)->toBe('Dilnoza Karimova')
        ->and($parent->phone)->toBe('+998937654321');
});

test('a parent can keep their own phone number typed in another form', function () {
    $parent = User::factory()->create(['phone' => '+998901234567']);

    $this->actingAs($parent)
        ->patch(route('profile.update'), [
            'name' => 'Dilnoza',
            'phone' => '90 123 45 67',
        ])
        ->assertSessionHasNoErrors();

    expect($parent->refresh()->phone)->toBe('+998901234567');
});

test('a parent cannot take a phone number another parent registered', function () {
    User::factory()->create(['phone' => '+998901234567']);
    $parent = User::factory()->create(['phone' => '+998937654321']);

    $this->actingAs($parent)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Dilnoza',
            'phone' => '901234567',
        ])
        ->assertSessionHasErrors([
            'phone' => "Bu telefon raqami allaqachon ro'yxatdan o'tgan.",
        ])
        ->assertRedirect(route('profile.edit'));

    expect($parent->refresh()->phone)->toBe('+998937654321');
});

test('a parent cannot save a phone number that is not an Uzbek number', function () {
    $parent = User::factory()->create(['phone' => '+998937654321']);

    $this->actingAs($parent)
        ->patch(route('profile.update'), [
            'name' => 'Dilnoza',
            'phone' => '12345',
        ])
        ->assertSessionHasErrors([
            'phone' => "Telefon raqamini to'g'ri kiriting, masalan: +998 90 123 45 67.",
        ]);

    expect($parent->refresh()->phone)->toBe('+998937654321');
});

test('after changing their phone number a parent logs in with the new one', function () {
    $parent = User::factory()->create(['phone' => '+998901234567']);

    $this->actingAs($parent)->patch(route('profile.update'), [
        'name' => $parent->name,
        'phone' => '+998 93 765 43 21',
    ]);
    $this->post(route('logout'));

    $this->post(route('login.store'), [
        'phone' => '937654321',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($parent);
});

test('a parent can delete their account', function () {
    $parent = User::factory()->create();

    $this->actingAs($parent)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($parent->fresh())->toBeNull();
});

test('the correct password is needed to delete an account', function () {
    $parent = User::factory()->create();

    $this->actingAs($parent)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors(['password' => "Parol noto'g'ri."])
        ->assertRedirect(route('profile.edit'));

    expect($parent->fresh())->not->toBeNull();
});
