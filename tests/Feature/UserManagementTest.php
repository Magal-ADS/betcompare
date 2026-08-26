<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_super_admin_can_create_an_operator(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);

        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->post(route('users.store'), $this->operatorPayload())
            ->assertForbidden();

        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)
            ->post(route('users.store'), $this->operatorPayload())
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'cliente@example.test',
            'role' => User::ROLE_OPERATOR,
        ]);
    }

    public function test_super_admin_can_update_an_operator_but_not_another_super_admin(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);

        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $anotherSuperAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)
            ->put(route('users.update', $operator), [
                'name' => 'Operador atualizado',
                'email' => 'atualizado@example.test',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $operator->id, 'email' => 'atualizado@example.test']);

        $this->actingAs($superAdmin)
            ->put(route('users.update', $anotherSuperAdmin), [
                'name' => 'Não altera',
                'email' => 'naoaltera@example.test',
            ])
            ->assertForbidden();
    }

    /**
     * @return array<string, string>
     */
    private function operatorPayload(): array
    {
        return [
            'name' => 'Cliente Firebets',
            'email' => 'cliente@example.test',
            'password' => 'senha-segura-123',
            'password_confirmation' => 'senha-segura-123',
        ];
    }
}
