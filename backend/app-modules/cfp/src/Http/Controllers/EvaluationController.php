<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PHPeste\Cfp\Http\Resources\ProposalResource;
use PHPeste\Cfp\Models\CfpCriterion;
use PHPeste\Cfp\Proposals\CfpWindow;
use PHPeste\Cfp\Proposals\ProposalPresenter;
use PHPeste\Cfp\Reviews\EvaluationBoard;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvaluationController
{
    public function __construct(
        private readonly EvaluationBoard $board,
        private readonly ProposalPresenter $presenter,
        private readonly CfpWindow $window,
        private readonly CurrentEdition $currentEdition,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $editionId = (string) $this->currentEdition->id();
        $board = $this->board->build($editionId);
        $presented = $this->presenter->present(collect(array_column($board['rows'], 'proposal')), true);
        $filter = $request->query('status');

        $rows = [];

        foreach ($board['rows'] as $index => $row) {
            if (is_string($filter) && $filter !== '' && $row['review_status'] !== $filter) {
                continue;
            }

            $rows[] = [
                ...$presented[$index]->resolve($request),
                'final_score' => $row['final_score'],
                'review_status' => $row['review_status'],
                'reviews_assigned' => $row['assigned'],
                'reviews_submitted' => $row['submitted'],
                'criterion_averages' => $row['criterion_averages'],
            ];
        }

        $scores = array_filter(array_column($board['rows'], 'final_score'), fn (?float $score): bool => $score !== null);
        $nordeste = array_filter($presented, fn (ProposalResource $resource): bool => (bool) ($resource->resolve($request)['nordeste'] ?? false));

        return response()->json([
            'summary' => [
                'proposals' => count($board['rows']),
                'reviewed' => count(array_filter($board['rows'], fn (array $row): bool => $row['review_status'] !== EvaluationBoard::PENDING)),
                'without_reviewer' => count(array_filter($board['rows'], fn (array $row): bool => $row['assigned'] === 0)),
                'average_score' => $scores === [] ? null : round(array_sum($scores) / count($scores), 2),
                'nordeste_percent' => $board['rows'] === [] ? null : (int) round(count($nordeste) * 100 / count($board['rows'])),
                'nordeste_quota_percent' => $this->window->settings($editionId)->nordeste_quota_percent,
            ],
            'criteria' => array_values($board['criteria']->map(fn (CfpCriterion $criterion): array => ['id' => $criterion->id, 'name' => $criterion->name, 'weight' => $criterion->weight])->all()),
            'data' => $rows,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $board = $this->board->build((string) $this->currentEdition->id());
        $presented = $this->presenter->present(collect(array_column($board['rows'], 'proposal')), true);
        $criteria = $board['criteria'];

        return response()->streamDownload(function () use ($board, $presented, $criteria, $request): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fwrite($output, "\u{FEFF}");
            fputcsv($output, ['Número', 'Título', 'Trilha', 'Situação', 'Nota final', 'Avaliações enviadas', 'Avaliações atribuídas', 'Nordeste', 'Autores', ...$criteria->map(fn (CfpCriterion $criterion): string => "{$criterion->name} (peso {$criterion->weight})")->all()], escape: '');

            foreach ($board['rows'] as $index => $row) {
                $presentedRow = $presented[$index]->resolve($request);
                $speakers = is_array($presentedRow['speakers'] ?? null) ? $presentedRow['speakers'] : [];

                fputcsv($output, [
                    $row['proposal']->number,
                    $this->safe($row['proposal']->title),
                    $this->safe((string) $row['proposal']->track?->name),
                    $row['review_status'],
                    $row['final_score'] === null ? '' : number_format($row['final_score'], 2, ',', ''),
                    $row['submitted'],
                    $row['assigned'],
                    ($presentedRow['nordeste'] ?? false) ? 'sim' : 'não',
                    $this->safe(implode('; ', array_map(fn (mixed $speaker): string => is_array($speaker) && is_string($speaker['name'] ?? null) ? $speaker['name'] : '', $speakers))),
                    ...$criteria->map(fn (CfpCriterion $criterion): string => isset($row['criterion_averages'][$criterion->id]) ? number_format($row['criterion_averages'][$criterion->id], 2, ',', '') : '')->all(),
                ], escape: '');
            }

            fclose($output);
        }, 'notas-cfp.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'{$value}" : $value;
    }
}
