<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Password for every demo account. It meets Password::defaults() so it can also be typed
     * into the change-password form as the "current password".
     */
    public const DEMO_PASSWORD = 'Demo#2026';

    /**
     * One demo account per role, for trying the portals locally (`php artisan db:seed`).
     * The admin account is the only way to get an administrator: the public registration form
     * never offers that role.
     */
    public function run(): void
    {
        User::factory()->borrower()->create([
            'name' => 'Demo Borrower',
            'email' => 'borrower@example.com',
            'password' => self::DEMO_PASSWORD,
        ]);

        User::factory()->investor()->create([
            'name' => 'Demo Investor',
            'email' => 'investor@example.com',
            'password' => self::DEMO_PASSWORD,
        ]);

        User::factory()->admin()->create([
            'name' => 'Demo Admin',
            'email' => 'admin@example.com',
            'password' => self::DEMO_PASSWORD,
        ]);
    }
}
