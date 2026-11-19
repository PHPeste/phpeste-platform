<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Overview;

use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Community\Data\OverviewContribution;
use PHPeste\Suppliers\Models\EditionSupplier;

class SuppliersOverviewContributor implements EditionOverviewContributor
{
    public function __construct(private readonly SupplierTotals $totals) {}

    public function contribute(string $editionId): OverviewContribution
    {
        $links = EditionSupplier::query()->forEdition($editionId)->with('quotes')->get();

        if ($links->isEmpty()) {
            return new OverviewContribution;
        }

        return new OverviewContribution(indicators: ['suppliers' => $this->totals->of($links)]);
    }
}
