<?php

use App\Models\User;

/*
| On the VPS the app sits behind Coolify's proxy, which ends HTTPS, and nginx.
| Both reach PHP from Docker's private networks (10.0.0.0/8 here). The
| browser's real scheme and address arrive in X-Forwarded-* headers, which
| are believed only from the addresses in TRUSTED_PROXIES.
*/

beforeEach(function () {
    config(['trustedproxy.proxies' => '10.0.0.0/8']);
});

test('the health check answers', function () {
    $this->get('/up')->assertOk();
});

test('behind the trusted proxy the app links back over https', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.5'])
        ->get('http://oqi.test'.route('picker', absolute: false), ['X-Forwarded-Proto' => 'https'])
        ->assertRedirect('https://oqi.test/login');
});

test('a forwarded host is not believed, so links never point at another site', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.5'])
        ->get('http://oqi.test'.route('picker', absolute: false), ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example'])
        ->assertRedirect('https://oqi.test/login');
});

test('a forwarded scheme from an untrusted address is ignored', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->get('http://oqi.test'.route('picker', absolute: false), ['X-Forwarded-Proto' => 'https'])
        ->assertRedirect('http://oqi.test/login');
});

test('forwarded headers are ignored when no proxy is trusted, as in development', function () {
    config(['trustedproxy.proxies' => '']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.5'])
        ->get('http://oqi.test'.route('picker', absolute: false), ['X-Forwarded-Proto' => 'https'])
        ->assertRedirect('http://oqi.test/login');
});

test('the login lockout counts each parent\'s own address, not the proxy\'s', function () {
    User::factory()->create(['phone' => '+998901234567']);

    $failLoginFrom = fn (string $address) => $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.5'])
        ->post(route('login.store'), ['phone' => '+998901234567', 'password' => 'wrong-password'], ['X-Forwarded-For' => $address]);

    foreach (range(1, 5) as $attempt) {
        $failLoginFrom('203.0.113.7')->assertSessionHasErrors('phone');
    }

    $failLoginFrom('203.0.113.7')->assertTooManyRequests();
    $failLoginFrom('198.51.100.20')->assertSessionHasErrors('phone');
});
