<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PHPeste\Cfp\Actions\UpdateProposal;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Cfp\Http\Requests\ListProposalsRequest;
use PHPeste\Cfp\Http\Requests\SaveProposalRequest;
use PHPeste\Cfp\Http\Resources\ProposalResource;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Proposals\CfpWindow;
use PHPeste\Cfp\Proposals\ProposalPresenter;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;

class AdminProposalController
{
    public function __construct(
        private readonly ProposalPresenter $presenter,
        private readonly CfpWindow $window,
        private readonly CurrentEdition $currentEdition,
    ) {}

    public function index(ListProposalsRequest $request): JsonResponse
    {
        Gate::authorize('cfp.view-proposals');

        $proposals = Proposal::query()
            ->with(['speakers', 'track'])
            ->where('status', '!=', ProposalStatus::Draft)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('track_id'), fn ($query) => $query->where('track_id', $request->string('track_id')->toString()))
            ->orderBy('number')
            ->get();

        return response()->json([
            'data' => array_map(fn (ProposalResource $resource): array => $resource->resolve($request), $this->presenter->present($proposals, $this->revealSpeakers())),
        ]);
    }

    public function show(Request $request): ProposalResource
    {
        Gate::authorize('cfp.view-proposals');

        return $this->presenter->one($this->proposal($request), $this->revealSpeakers());
    }

    public function update(SaveProposalRequest $request, UpdateProposal $update): ProposalResource
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        return $this->presenter->one($update->handle($this->proposal($request), $request->proposal(), true)->load('track'), true);
    }

    private function revealSpeakers(): bool
    {
        return Gate::allows(EditionPermission::ManageProgram->value)
            || !$this->window->settings((string) $this->currentEdition->id())->anonymous_review;
    }

    private function proposal(Request $request): Proposal
    {
        $id = $request->route('proposal');

        abort_unless(is_string($id), 404);

        return Proposal::query()->with(['speakers', 'track'])->where('status', '!=', ProposalStatus::Draft)->whereKey($id)->firstOrFail();
    }
}
