<?php

declare(strict_types=1);

namespace PHPeste\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PHPeste\Community\Enums\BrazilianState;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Community;
use PHPeste\Community\Models\Edition;

class CommunitySeeder extends Seeder
{
    public function run(): void
    {
        $organizerId = DB::table('users')->where('email', 'test@example.com')->value('id');

        $community = Community::factory()->create([
            'name' => 'PHP-PB',
            'slug' => 'php-pb',
            'created_by' => $organizerId,
        ]);

        $edition = Edition::factory()->for($community)->create([
            'name' => 'PHPeste 2027',
            'slug' => '2027',
            'starts_on' => '2027-10-08',
            'ends_on' => '2027-10-09',
            'city' => 'João Pessoa',
            'state' => BrazilianState::PB,
        ]);

        if (is_string($organizerId)) {
            $edition->members()->create(['user_id' => $organizerId, 'role' => EditionRole::Organizer]);
        }
    }
}
