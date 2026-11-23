<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('email verification, password reset, two-factor login and passkey routes are not registered', function (string $routeName) {
    expect(Route::has($routeName))->toBeFalse();
})->with([
    'verification.notice',
    'verification.verify',
    'verification.send',
    'password.request',
    'password.email',
    'password.reset',
    'password.update',
    'two-factor.login',
    'two-factor.login.store',
    'two-factor.enable',
    'two-factor.disable',
    'two-factor.confirm',
    'two-factor.qr-code',
    'two-factor.secret-key',
    'two-factor.recovery-codes',
    'two-factor.regenerate-recovery-codes',
    'passkey.login',
    'passkey.login-options',
    'passkey.confirm',
    'passkey.confirm-options',
    'passkey.store',
    'passkey.registration-options',
    'passkey.destroy',
    'well-known.passkeys',
]);

test('guests get not found from the removed screens', function (string $method, string $uri) {
    $this->call($method, $uri)->assertNotFound();
})->with([
    ['GET', '/forgot-password'],
    ['POST', '/forgot-password'],
    ['GET', '/reset-password/some-token'],
    ['POST', '/reset-password'],
    ['GET', '/two-factor-challenge'],
    ['POST', '/two-factor-challenge'],
    ['GET', '/passkeys/login/options'],
    ['POST', '/passkeys/login'],
    ['GET', '/.well-known/passkey-endpoints'],
]);

test('logged-in parents get not found from the removed screens', function (string $method, string $uri) {
    $this->actingAs(User::factory()->create())
        ->call($method, $uri)
        ->assertNotFound();
})->with([
    ['GET', '/email/verify'],
    ['POST', '/email/verification-notification'],
    ['POST', '/user/two-factor-authentication'],
    ['GET', '/user/two-factor-qr-code'],
    ['GET', '/user/two-factor-recovery-codes'],
    ['GET', '/user/passkeys/options'],
    ['POST', '/user/passkeys'],
]);
