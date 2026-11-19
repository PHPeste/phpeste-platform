<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Overview;

use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Community\Data\OverviewContribution;
use PHPeste\Community\Enums\EditionPermission;

class SponsorsOverviewContributor implements EditionOverviewContributor
{
    public function __construct(private readonly SponsorTotals $totals) {}

    public function contribute(string $editionId): OverviewContribution
    {
        $totals = $this->totals->of($editionId);
        $overdue = $totals['deliverables_overdue'];

        if ($totals['goal'] === null && array_sum(array_column($totals['stages'], 'count')) === 0) {
            return new OverviewContribution;
        }

        return new OverviewContribution(
            indicators: ['sponsors' => [
                'goal' => $totals['goal'],
                'committed' => $totals['committed'],
                'received' => $totals['received'],
                'pipeline' => $totals['pipeline'],
                'stages' => $totals['stages'],
            ]],
            pending: $overdue === 0 ? [] : [[
                'key' => 'sponsor_deliverables_overdue',
                'title' => sprintf('%d %s de patrocinador %s', $overdue, $overdue === 1 ? 'entrega' : 'entregas', $overdue === 1 ? 'atrasada' : 'atrasadas'),
                'tone' => 'warn',
                'tag' => 'Patrocínio',
                'action_label' => 'Ver entregas',
                'action_path' => '/patrocinadores/entregas?atrasadas=1',
                'priority' => 2,
                'permission' => EditionPermission::ManageSponsors,
            ]],
        );
    }
}
