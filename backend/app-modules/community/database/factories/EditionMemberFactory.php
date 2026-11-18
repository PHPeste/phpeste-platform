<?php

declare(strict_types=1);

namespace PHPeste\Community\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;

/**
 * @extends Factory<EditionMember>
 */
class EditionMemberFactory extends Factory
{
    protected $model = EditionMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edition_id' => Edition::factory(),
            'role' => EditionRole::Volunteer,
        ];
    }
}
