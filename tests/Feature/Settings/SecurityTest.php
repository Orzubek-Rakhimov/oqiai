<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('the password settings page is shown without a password confirmation step', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->has('passwordRules')
            ->missing('canManageTwoFactor')
            ->missing('canManagePasskeys')
            ->missing('passkeys'),
        );
});

test('a parent changes their password', function () {
    $parent = User::factory()->create();

    $this->actingAs($parent)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('new-password', $parent->refresh()->password))->toBeTrue();
});

test('the current password must be correct to change the password', function () {
    $parent = User::factory()->create();

    $this->actingAs($parent)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasErrors(['current_password' => "Parol noto'g'ri."])
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('password', $parent->refresh()->password))->toBeTrue();
});

test('the new password must be typed the same twice', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'other-password',
        ])
        ->assertSessionHasErrors(['password' => "Parol tasdig'i mos kelmadi."]);
});
