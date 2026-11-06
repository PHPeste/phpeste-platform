<?php

declare(strict_types=1);

namespace PHPeste\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use PHPeste\Identity\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
