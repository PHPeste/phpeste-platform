<?php

declare(strict_types=1);

namespace PHPeste\Community\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionInvitation;

/**
 * @extends Factory<EditionInvitation>
 */
class EditionInvitationFactory extends Factory
{
    protected $model = EditionInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edition_id' => Edition::factory(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'role' => EditionRole::Finance,
            'token_hash' => EditionInvitation::hashToken(Str::random(48)),
            'expires_at' => now()->addDays(7),
        ];
    }

    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes): array => ['token_hash' => EditionInvitation::hashToken($token)]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => now()->subMinute()]);
    }
}
