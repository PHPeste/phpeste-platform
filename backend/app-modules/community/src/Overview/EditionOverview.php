<?php

declare(strict_types=1);

namespace PHPeste\Community\Overview;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;

class EditionOverview
{
    private const array STAGE_NOTES = [
        'draft' => 'Preparação',
        'cfp_open' => 'Chamada de trabalhos',
        'sales_open' => 'Venda de ingressos',
        'ongoing' => 'Evento',
        'closed' => 'Pós-evento',
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(Edition $edition, Authenticatable $user): array
    {
        $indicators = ['tickets' => null, 'revenue' => null, 'proposals' => null, 'speakers' => null];
        $sections = ['tickets_per_week' => null, 'nordeste' => null];
        $pending = [];
        $milestones = [];

        foreach (app()->tagged(EditionOverviewContributor::TAG) as $contributor) {
            if (!$contributor instanceof EditionOverviewContributor) {
                continue;
            }

            $contribution = $contributor->contribute($edition->id);
            $indicators = [...$indicators, ...$contribution->indicators];
            $sections = [...$sections, ...$contribution->sections];
            $pending = [...$pending, ...$contribution->pending];
            $milestones = [...$milestones, ...$contribution->milestones];
        }

        if ($edition->starts_on !== null) {
            $milestones[] = ['key' => 'event', 'title' => 'Evento', 'description' => $edition->name, 'date' => $edition->starts_on->toDateString()];
        }

        $gate = Gate::forUser($user);
        $pending = array_values(array_filter($pending, fn (array $item): bool => $gate->allows($item['permission']->value, $edition->id)));
        usort($pending, fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        $today = now()->toDateString();
        $milestones = array_values(array_filter($milestones, fn (array $milestone): bool => $milestone['date'] >= $today));
        usort($milestones, fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return [
            'edition' => [
                'id' => $edition->id,
                'name' => $edition->name,
                'starts_on' => $edition->starts_on?->toDateString(),
                'ends_on' => $edition->ends_on?->toDateString(),
                'city' => $edition->city,
                'state' => $edition->state?->value,
            ],
            'stage' => [
                'current' => $edition->status->value,
                'days_until_event' => $edition->starts_on === null || $edition->starts_on->isPast()
                    ? null
                    : (int) today()->diffInDays($edition->starts_on->copy()->startOfDay()),
                'steps' => $this->steps($edition->status),
            ],
            'indicators' => $indicators,
            'sections' => $sections,
            'pending' => array_map(fn (array $item): array => array_diff_key($item, ['permission' => true, 'priority' => true]), $pending),
            'milestones' => array_slice($milestones, 0, 6),
        ];
    }

    /**
     * @return list<array{status: string, label: string, note: string, state: string}>
     */
    private function steps(EditionStatus $current): array
    {
        $order = EditionStatus::cases();
        $currentIndex = (int) array_search($current, $order, true);

        return array_map(fn (EditionStatus $status, int $index): array => [
            'status' => $status->value,
            'label' => $status->label(),
            'note' => self::STAGE_NOTES[$status->value],
            'state' => match (true) {
                $index < $currentIndex => 'done',
                $index === $currentIndex => 'current',
                $index === $currentIndex + 1 => 'next',
                default => 'upcoming',
            },
        ], $order, array_keys($order));
    }
}
