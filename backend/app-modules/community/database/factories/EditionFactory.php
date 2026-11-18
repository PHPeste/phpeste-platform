<?php

declare(strict_types=1);

namespace PHPeste\Community\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PHPeste\Community\Enums\BrazilianState;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Community;
use PHPeste\Community\Models\Edition;

/**
 * @extends Factory<Edition>
 */
class EditionFactory extends Factory
{
    protected $model = Edition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2_027, 2_999);
        $startsOn = fake()->dateTimeBetween("{$year}-01-01", "{$year}-12-20");

        return [
            'community_id' => Community::factory(),
            'name' => "PHPeste {$year}",
            'slug' => (string) $year,
            'starts_on' => $startsOn->format('Y-m-d'),
            'ends_on' => $startsOn->modify('+1 day')->format('Y-m-d'),
            'city' => 'João Pessoa',
            'state' => BrazilianState::PB,
            'timezone' => 'America/Fortaleza',
            'status' => EditionStatus::Draft,
        ];
    }

    public function status(EditionStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }
}
