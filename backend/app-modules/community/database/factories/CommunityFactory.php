<?php

declare(strict_types=1);

namespace PHPeste\Community\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use PHPeste\Community\Models\Community;

/**
 * @extends Factory<Community>
 */
class CommunityFactory extends Factory
{
    protected $model = Community::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'PHP-'.fake()->unique()->lexify('??????');

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'created_by' => null,
        ];
    }

    public function createdBy(string $userId): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_by' => $userId,
        ]);
    }
}
