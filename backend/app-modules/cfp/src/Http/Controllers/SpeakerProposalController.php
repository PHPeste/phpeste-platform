<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Controllers;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPeste\Cfp\Actions\AddCoauthor;
use PHPeste\Cfp\Actions\CreateProposal;
use PHPeste\Cfp\Actions\RemoveCoauthor;
use PHPeste\Cfp\Actions\SubmitProposal;
use PHPeste\Cfp\Actions\UpdateProposal;
use PHPeste\Cfp\Actions\WithdrawProposal;
use PHPeste\Cfp\Enums\ProposalDecision;
use PHPeste\Cfp\Http\Requests\AddCoauthorRequest;
use PHPeste\Cfp\Http\Requests\SaveProposalRequest;
use PHPeste\Cfp\Http\Resources\ProposalResource;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Proposals\ProposalPresenter;
use PHPeste\Community\Tenancy\CurrentEdition;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SpeakerProposalController
{
    public function __construct(
        private readonly CurrentEdition $currentEdition,
        private readonly ProposalPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        $proposals = Proposal::query()
            ->whereHas('speakers', fn (Builder $query) => $query->where('user_id', $userId))
            ->with(['speakers', 'track', 'confirmation'])
            ->orderBy('number')
            ->get();

        $presented = $this->presenter->present($proposals, true);

        return response()->json(['data' => array_map(fn (ProposalResource $resource, Proposal $proposal): array => [...$resource->resolve($request), 'confirmation' => $this->confirmation($proposal)], $presented, $proposals->all())]);
    }

    public function store(SaveProposalRequest $request, CreateProposal $create): JsonResponse
    {
        $proposal = $create->handle((string) $this->currentEdition->id(), $this->userId($request), $request->proposal());

        return $this->presenter->one($proposal->load('track'), true)->response()->setStatusCode(201);
    }

    public function show(Request $request): JsonResponse
    {
        $proposal = $this->proposal($request, ownerOnly: false);

        return response()->json(['data' => [...$this->presenter->one($proposal, true)->resolve($request), 'confirmation' => $this->confirmation($proposal)]]);
    }

    public function update(SaveProposalRequest $request, UpdateProposal $update): ProposalResource
    {
        return $this->presenter->one($update->handle($this->proposal($request, ownerOnly: false), $request->proposal(), false)->load('track'), true);
    }

    public function submit(Request $request, SubmitProposal $submit): ProposalResource
    {
        return $this->presenter->one($submit->handle($this->proposal($request, ownerOnly: true))->load('track'), true);
    }

    public function withdraw(Request $request, WithdrawProposal $withdraw): ProposalResource
    {
        return $this->presenter->one($withdraw->handle($this->proposal($request, ownerOnly: true))->load('track'), true);
    }

    public function addCoauthor(AddCoauthorRequest $request, AddCoauthor $add): ProposalResource
    {
        return $this->presenter->one($add->handle($this->proposal($request, ownerOnly: true), $request->string('email')->toString())->load('track'), true);
    }

    public function removeCoauthor(Request $request, RemoveCoauthor $remove): ProposalResource
    {
        $userId = $request->route('user');

        abort_unless(is_string($userId), 404);

        return $this->presenter->one($remove->handle($this->proposal($request, ownerOnly: true), $userId)->load('track'), true);
    }

    private function proposal(Request $request, bool $ownerOnly): Proposal
    {
        $id = $request->route('proposal');

        abort_unless(is_string($id), 404);

        $proposal = Proposal::query()->with(['speakers', 'track', 'confirmation'])->whereKey($id)->firstOrFail();
        $userId = $this->userId($request);

        abort_unless($proposal->hasSpeaker($userId), 404);

        throw_if($ownerOnly && $proposal->ownerId() !== $userId, AccessDeniedHttpException::class, 'Só quem criou a proposta pode fazer isso.');

        return $proposal;
    }

    /**
     * @return array{status: string, status_label: string, deadline_at: string, responded_at: string|null}|null
     */
    private function confirmation(Proposal $proposal): ?array
    {
        $confirmation = $proposal->confirmation;

        if ($proposal->decision !== ProposalDecision::Accepted || $confirmation === null) {
            return null;
        }

        return [
            'status' => $confirmation->status->value,
            'status_label' => $confirmation->status->label(),
            'deadline_at' => $confirmation->deadline_at->clone()->utc()->toIso8601ZuluString(),
            'responded_at' => $confirmation->responded_at?->clone()->utc()->toIso8601ZuluString(),
        ];
    }

    private function userId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
