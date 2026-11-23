<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The starter dashboard was replaced by the "Who is reading?" picker, which is
 * now the page a logged-in Parent lands on.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('picker'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_picker()
    {
        $user = User::factory()->has(Child::factory())->create();
        $this->actingAs($user);

        $response = $this->get(route('picker'));
        $response->assertOk();
    }
}
