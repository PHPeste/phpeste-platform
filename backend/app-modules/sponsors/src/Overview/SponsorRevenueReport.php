<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Overview;

use PHPeste\Sponsors\Contracts\SponsorRevenue;
use PHPeste\Sponsors\Enums\SponsorshipStage;
use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Sponsors\Models\SponsorshipPayment;

class SponsorRevenueReport implements SponsorRevenue
{
    public function summary(string $editionId): array
    {
        $deals = Sponsorship::query()->forEdition($editionId)
            ->whereIn('stage', SponsorshipStage::committedValues())
            ->withSum('payments as received_cents', 'amount_cents')
            ->get(['id', 'value_cents']);

        $outstanding = 0;

        foreach ($deals as $deal) {
            $outstanding += max(0, $deal->value_cents - (int) $deal->getAttribute('received_cents'));
        }

        return [
            'received' => (int) SponsorshipPayment::query()->forEdition($editionId)->sum('amount_cents'),
            'outstanding' => $outstanding,
            'deals' => $deals->count(),
        ];
    }
}
