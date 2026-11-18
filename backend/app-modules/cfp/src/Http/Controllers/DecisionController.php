<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Actions\ApplyDecisions;
use PHPeste\Cfp\Decisions\DecisionBoard;
use PHPeste\Cfp\Enums\ProposalDecision;
use PHPeste\Cfp\Http\Requests\DecisionsRequest;
use PHPeste\Cfp\Proposals\ProposalPresenter;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;

class DecisionController
{
    public function __construct(
        private readonly DecisionBoard $board,
        private readonly ProposalPresenter $presenter,
        private readonly CurrentEdition $currentEdition,
    ) {}

    public function ranking(Request $request): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $editionId = $this->editionId();
        $tracks = [];

        foreach ($this->board->ranking($editionId) as $group) {
            $presented = $this->presenter->present(collect(array_column($group['proposals'], 'proposal')), true);

            $tracks[] = [
                'track' => $group['track'],
                'proposals' => array_map(fn (array $row, $resource): array => [
                    ...$resource->resolve($request),
                    'final_score' => $row['final_score'],
                    'rank' => $row['rank'],
                    'decision' => $row['proposal']->decision?->value,
                    'suggested_decision' => $row['suggested']->value,
                    'tie_at_cutoff' => $row['tie_at_cutoff'],
                ], $group['proposals'], $presented),
            ];
        }

        return response()->json(['data' => ['tracks' => $tracks, 'outcome' => $this->board->simulate($editionId, [])]]);
    }

    public function simulate(DecisionsRequest $request): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        return response()->json(['data' => $this->board->simulate($this->editionId(), $request->decisions())]);
    }

    public function apply(DecisionsRequest $request, ApplyDecisions $apply): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $decisions = array_filter($request->decisions(), fn (?ProposalDecision $decision): bool => $decision instanceof ProposalDecision);

        if (count($decisions) !== count($request->decisions())) {
            throw ValidationException::withMessages(['decisions' => 'Para aplicar, toda proposta enviada precisa de uma decisão: aprovar, lista de espera ou recusar.']);
        }

        $changed = $apply->handle($this->editionId(), $decisions, (string) $request->user()?->getAuthIdentifier());

        return response()->json(['data' => ['changed' => $changed, 'outcome' => $this->board->simulate($this->editionId(), [])]]);
    }

    private function editionId(): string
    {
        return (string) $this->currentEdition->id();
    }
}
