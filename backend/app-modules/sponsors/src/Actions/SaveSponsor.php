<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPeste\Sponsors\Attachments\AttachmentStore;
use PHPeste\Sponsors\Models\Sponsor;
use PHPeste\Sponsors\Models\SponsorAttachment;
use PHPeste\Sponsors\Models\SponsorshipPayment;

class SaveSponsor
{
    /**
     * @param  array{name?: string, website?: string|null, contact_name?: string|null, contact_email?: string|null, contact_phone?: string|null, notes?: string|null}  $attributes
     */
    public function handle(string $editionId, ?Sponsor $sponsor, array $attributes): Sponsor
    {
        $name = $attributes['name'] ?? null;

        if ($name !== null && Sponsor::query()->forEdition($editionId)->where('name', $name)->when($sponsor instanceof Sponsor, fn ($query) => $query->whereKeyNot($sponsor?->id))->exists()) {
            throw ValidationException::withMessages(['name' => 'Esta empresa já está cadastrada nesta edição.']);
        }

        if (!$sponsor instanceof Sponsor) {
            return Sponsor::create(['edition_id' => $editionId, ...$attributes]);
        }

        $sponsor->update($attributes);

        return $sponsor;
    }

    public function delete(Sponsor $sponsor): void
    {
        $paid = SponsorshipPayment::query()->forEdition($sponsor->edition_id)
            ->whereIn('sponsorship_id', $sponsor->sponsorships()->select('id'))
            ->exists();

        if ($paid) {
            throw ValidationException::withMessages(['sponsor' => 'Esta empresa tem pagamento registrado e não pode ser removida.']);
        }

        $paths = SponsorAttachment::query()->forEdition($sponsor->edition_id)->where('sponsor_id', $sponsor->id)->pluck('path')->all();

        DB::transaction(fn () => $sponsor->delete());

        Storage::disk(AttachmentStore::disk())->delete($paths);
    }
}
