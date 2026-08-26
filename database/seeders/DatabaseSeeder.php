<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = config('oddradar.super_admin.email');
        $password = config('oddradar.super_admin.password');

        if (! is_string($email) || ! is_string($password) || $email === '' || $password === '') {
            return;
        }

        User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => config('oddradar.super_admin.name'),
                'password' => Hash::make($password),
                'role' => User::ROLE_SUPER_ADMIN,
            ],
        );
    }
}
