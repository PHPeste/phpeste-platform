<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Sponsors\Enums\SponsorshipStage;
use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Sponsors\Models\SponsorTier;

class SaveSponsorTier
{
    /**
     * @param  array{name?: string, price_cents?: int, slots?: int|null, benefits?: list<string>, position?: int}  $attributes
     */
    public function handle(string $editionId, ?SponsorTier $tier, array $attributes): SponsorTier
    {
        $name = $attributes['name'] ?? $tier?->name;

        if ($name !== null && SponsorTier::query()->forEdition($editionId)->where('name', $name)->when($tier instanceof SponsorTier, fn ($query) => $query->whereKeyNot($tier?->id))->exists()) {
            throw ValidationException::withMessages(['name' => 'Já existe uma cota com esse nome.']);
        }

        if ($tier instanceof SponsorTier && array_key_exists('slots', $attributes) && $attributes['slots'] !== null) {
            $taken = Sponsorship::query()->forEdition($editionId)->where('sponsor_tier_id', $tier->id)->whereIn('stage', SponsorshipStage::committedValues())->count();

            if ($attributes['slots'] < $taken) {
                throw ValidationException::withMessages(['slots' => sprintf('Esta cota já tem %d acordos fechados; não dá para ter menos vagas que isso.', $taken)]);
            }
        }

        if (!$tier instanceof SponsorTier) {
            return SponsorTier::create([
                'edition_id' => $editionId,
                'benefits' => [],
                'position' => (int) SponsorTier::query()->forEdition($editionId)->max('position') + 1,
                ...$attributes,
            ]);
        }

        $tier->update($attributes);

        return $tier;
    }

    public function delete(SponsorTier $tier): void
    {
        if (Sponsorship::query()->forEdition($tier->edition_id)->where('sponsor_tier_id', $tier->id)->exists()) {
            throw ValidationException::withMessages(['tier' => 'Esta cota tem acordos. Troque a cota deles antes de remover.']);
        }

        $tier->delete();
    }
}
