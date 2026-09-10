<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_the_configured_super_admin(): void
    {
        config()->set('oddradar.super_admin', [
            'name' => 'Administrador OddRadar',
            'email' => 'jbarbosafenerick@gmail.com',
            'password' => 'senha-segura-123',
        ]);

        app(DatabaseSeeder::class)->run();

        $superAdmin = User::query()->sole();

        $this->assertSame('Administrador OddRadar', $superAdmin->name);
        $this->assertSame('jbarbosafenerick@gmail.com', $superAdmin->email);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $superAdmin->role);
        $this->assertTrue(Hash::check('senha-segura-123', $superAdmin->password));
    }
}
