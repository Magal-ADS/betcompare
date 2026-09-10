<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_page_has_a_password_visibility_control(): void
    {
        $this->get(route('login'))
            ->assertSeeHtml('data-password-visibility-toggle')
            ->assertSeeHtml('aria-controls="password"')
            ->assertSeeText('Mostrar');
    }

    public function test_user_can_log_in_and_log_out(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);

        $user = User::factory()->create([
            'email' => 'operador@example.test',
            'password' => Hash::make('uma-senha-segura'),
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'uma-senha-segura',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);

        User::factory()->create([
            'email' => 'operador@example.test',
            'password' => Hash::make('uma-senha-segura'),
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'operador@example.test',
                'password' => 'senha-incorreta',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
