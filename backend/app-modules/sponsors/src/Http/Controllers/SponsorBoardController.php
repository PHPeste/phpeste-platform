<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Sponsors\Actions\UpdateSponsorSettings;
use PHPeste\Sponsors\Enums\SponsorshipStage;
use PHPeste\Sponsors\Http\People;
use PHPeste\Sponsors\Http\Requests\UpdateSponsorSettingsRequest;
use PHPeste\Sponsors\Http\Resources\SponsorshipResource;
use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Sponsors\Overview\SponsorTotals;

/**
 * O funil em colunas, com os indicadores do topo (meta, fechado, recebido,
 * em negociação).
 */
class SponsorBoardController
{
    use Concerns;

    public function show(Request $request, CurrentEdition $edition, SponsorTotals $totals, People $people): JsonResponse
    {
        $this->authorizeSponsors();

        $deals = Sponsorship::query()->with(['sponsor:id,name', 'tier:id,name'])
            ->withSum('payments as received_cents', 'amount_cents')
            ->orderBy('position')->oldest()->orderBy('id')
            ->get();
        $people->load($deals->pluck('owner_id')->all());
        $summary = $totals->of((string) $edition->id());

        return response()->json(['data' => [
            'indicators' => [
                'goal' => $this->money($summary['goal']),
                'committed' => $this->money($summary['committed']),
                'received' => $this->money($summary['received']),
                'pipeline' => $this->money($summary['pipeline']),
                'deliverables_overdue' => $summary['deliverables_overdue'],
            ],
            'columns' => array_map(fn (SponsorshipStage $stage): array => [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'count' => $summary['stages'][$stage->value]['count'],
                'value' => $this->money($summary['stages'][$stage->value]['value']),
                'cards' => $deals->where('stage', $stage)->values()->map(fn (Sponsorship $deal): array => new SponsorshipResource($deal)->toArray($request))->all(),
            ], SponsorshipStage::cases()),
        ]]);
    }

    public function settings(UpdateSponsorSettingsRequest $request, CurrentEdition $edition, UpdateSponsorSettings $update): JsonResponse
    {
        $this->authorizeSponsors();

        $settings = $update->handle((string) $edition->id(), $request->filled('goal_cents') ? $request->integer('goal_cents') : null);

        return response()->json(['data' => ['goal' => $this->money($settings->goal_cents)]]);
    }

    /**
     * @return array{amount: int, currency: string}|null
     */
    private function money(?int $cents): ?array
    {
        return $cents === null ? null : ['amount' => $cents, 'currency' => 'BRL'];
    }
}
