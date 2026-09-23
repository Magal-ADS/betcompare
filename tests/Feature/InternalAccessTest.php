<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InternalAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_dashboard_requires_an_authenticated_session(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_dashboard_redirect_keeps_https_behind_a_trusted_proxy(): void
    {
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('http://oddradar.test/')
            ->assertRedirect('https://oddradar.test/login');
    }

    public function test_authenticated_operator_can_access_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSee('OddRadar');
    }
}
