<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the login screen is rendered', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->missing('canResetPassword'),
        );
});

test('a parent logs in with their phone number typed in any local form', function (string $typedPhone) {
    $parent = User::factory()->create(['phone' => '+998901234567']);

    $response = $this->post(route('login.store'), [
        'phone' => $typedPhone,
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('picker', absolute: false));

    $this->assertAuthenticatedAs($parent);
})->with([
    'canonical' => '+998901234567',
    'without plus' => '998901234567',
    'without country code' => '901234567',
    'with spaces' => '+998 90 123 45 67',
    'with dashes' => '90-123-45-67',
]);

test('a parent cannot log in with a wrong password', function () {
    User::factory()->create(['phone' => '+998901234567']);

    $this->post(route('login.store'), [
        'phone' => '+998901234567',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors([
        'phone' => "Telefon raqami yoki parol noto'g'ri.",
    ]);

    $this->assertGuest();
});

test('an unregistered phone number cannot log in', function () {
    $this->post(route('login.store'), [
        'phone' => '+998907654321',
        'password' => 'password',
    ])->assertSessionHasErrors('phone');

    $this->assertGuest();
});

test('a parent signs up and can log in again later', function () {
    $this->post(route('register.store'), [
        'name' => 'Dilnoza',
        'phone' => '90 123 45 67',
        'password' => 'password',
    ]);

    $this->post(route('logout'));
    $this->assertGuest();

    $this->post(route('login.store'), [
        'phone' => '+998-90-123-45-67',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs(User::sole());
});

test('a parent can log out', function () {
    $parent = User::factory()->create();

    $this->actingAs($parent)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('login attempts are rate limited per phone number whatever form it is typed in', function () {
    User::factory()->create(['phone' => '+998901234567']);

    foreach (['+998901234567', '901234567', '90 123 45 67', '998901234567', '90-123-45-67'] as $typedPhone) {
        $this->post(route('login.store'), [
            'phone' => $typedPhone,
            'password' => 'wrong-password',
        ]);
    }

    $this->post(route('login.store'), [
        'phone' => '+998 90 123 45 67',
        'password' => 'password',
    ])->assertTooManyRequests();

    $this->assertGuest();
});

test('the rate limit for one phone number does not block another', function () {
    $other = User::factory()->create(['phone' => '+998907654321']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), [
            'phone' => '+998901234567',
            'password' => 'wrong-password',
        ]);
    }

    $this->post(route('login.store'), [
        'phone' => '+998907654321',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($other);
});
