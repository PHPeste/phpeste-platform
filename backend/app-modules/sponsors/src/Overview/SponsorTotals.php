<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Overview;

use PHPeste\Sponsors\Enums\DeliverableStatus;
use PHPeste\Sponsors\Enums\SponsorshipStage;
use PHPeste\Sponsors\Models\SponsorDeliverable;
use PHPeste\Sponsors\Models\SponsorSetting;
use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Sponsors\Models\SponsorshipPayment;

/**
 * Números do patrocínio da edição, em centavos: meta, fechado (fechado e
 * pago), recebido (soma dos pagamentos), em negociação (prospecção e
 * negociação) e quantos acordos e quanto valor há em cada etapa.
 */
final class SponsorTotals
{
    /**
     * @return array{goal: int|null, committed: int, received: int, pipeline: int, stages: array<string, array{count: int, value: int}>, deliverables_overdue: int}
     */
    public function of(string $editionId): array
    {
        $stages = [];

        foreach (SponsorshipStage::cases() as $stage) {
            $stages[$stage->value] = ['count' => 0, 'value' => 0];
        }

        $rows = Sponsorship::query()->forEdition($editionId)
            ->selectRaw('stage, count(*) as total, sum(value_cents) as value')
            ->groupBy('stage')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $stage = SponsorshipStage::tryFrom(is_string($row->stage) ? $row->stage : '');

            if ($stage instanceof SponsorshipStage) {
                $stages[$stage->value] = [
                    'count' => is_numeric($row->total) ? (int) $row->total : 0,
                    'value' => is_numeric($row->value) ? (int) $row->value : 0,
                ];
            }
        }

        return [
            'goal' => SponsorSetting::query()->forEdition($editionId)->value('goal_cents'),
            'committed' => $stages[SponsorshipStage::Closed->value]['value'] + $stages[SponsorshipStage::Paid->value]['value'],
            'received' => (int) SponsorshipPayment::query()->forEdition($editionId)->sum('amount_cents'),
            'pipeline' => $stages[SponsorshipStage::Prospecting->value]['value'] + $stages[SponsorshipStage::Negotiating->value]['value'],
            'stages' => $stages,
            'deliverables_overdue' => SponsorDeliverable::query()->forEdition($editionId)
                ->where('status', '!=', DeliverableStatus::Done->value)
                ->whereDate('due_on', '<', now()->toDateString())
                ->count(),
        ];
    }
}
