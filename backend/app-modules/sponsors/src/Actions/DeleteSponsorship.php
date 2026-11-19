<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Sponsors\Models\SponsorshipPayment;

class DeleteSponsorship
{
    public function handle(Sponsorship $sponsorship): void
    {
        if (SponsorshipPayment::query()->forEdition($sponsorship->edition_id)->where('sponsorship_id', $sponsorship->id)->exists()) {
            throw ValidationException::withMessages(['sponsorship' => 'Este acordo tem pagamento registrado. Marque como perdido em vez de remover.']);
        }

        $sponsorship->delete();
    }
}
