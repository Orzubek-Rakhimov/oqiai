<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the sign-up screen is rendered', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/register'));
});

test('a parent signs up with name, phone number and password and is logged in', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Dilnoza',
        'phone' => '+998901234567',
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $parent = User::sole();
    expect($parent->name)->toBe('Dilnoza')
        ->and($parent->phone)->toBe('+998901234567');
});

test('a phone number typed in a local form is stored as +998 and 9 digits', function (string $typedPhone) {
    $this->post(route('register.store'), [
        'name' => 'Dilnoza',
        'phone' => $typedPhone,
        'password' => 'password',
    ])->assertSessionHasNoErrors();

    expect(User::sole()->phone)->toBe('+998901234567');
})->with([
    'canonical' => '+998901234567',
    'without plus' => '998901234567',
    'without country code' => '901234567',
    'with spaces' => '+998 90 123 45 67',
    'with dashes' => '90-123-45-67',
    'with brackets and spaces' => '+998 (90) 123-45-67',
    'surrounded by whitespace' => '  90 123 45 67 ',
]);

test('a phone number that is not an Uzbek number is rejected in Uzbek', function (string $typedPhone) {
    $this->post(route('register.store'), [
        'name' => 'Dilnoza',
        'phone' => $typedPhone,
        'password' => 'password',
    ])->assertSessionHasErrors([
        'phone' => "Telefon raqamini to'g'ri kiriting, masalan: +998 90 123 45 67.",
    ]);

    $this->assertGuest();
    expect(User::count())->toBe(0);
})->with([
    'too short' => '90123456',
    'too long' => '+9989012345678',
    'other country' => '+79012345678',
    'letters' => 'telefon',
]);

test('a phone number that is already registered is rejected with an Uzbek message', function () {
    User::factory()->create(['phone' => '+998901234567']);

    $this->post(route('register.store'), [
        'name' => 'Dilnoza',
        'phone' => '90 123-45-67',
        'password' => 'password',
    ])->assertSessionHasErrors([
        'phone' => "Bu telefon raqami allaqachon ro'yxatdan o'tgan. Hisobingiz bo'lsa, tizimga kiring.",
    ]);

    $this->assertGuest();
    expect(User::count())->toBe(1);
});

test('sign-up needs a name and a password', function () {
    $this->post(route('register.store'), [
        'name' => '',
        'phone' => '+998901234567',
        'password' => '',
    ])->assertSessionHasErrors([
        'name' => "Ism maydonini to'ldiring.",
        'password' => "Parol maydonini to'ldiring.",
    ]);

    $this->assertGuest();
});
