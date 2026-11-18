<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Proposals;

use PHPeste\Cfp\Confirmations\WaitlistPromotions;
use PHPeste\Cfp\Decisions\CoverageCalculator;
use PHPeste\Cfp\Enums\CfpStatus;
use PHPeste\Cfp\Enums\ConfirmationStatus;
use PHPeste\Cfp\Enums\ProposalDecision;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Cfp\Models\CfpTrack;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\ProposalSpeaker;
use PHPeste\Cfp\Models\SpeakerConfirmation;
use PHPeste\Cfp\Reviews\EvaluationBoard;
use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Community\Data\OverviewContribution;
use PHPeste\Community\Enums\BrazilianState;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Identity\Contracts\SpeakerProfiles;

class CfpOverviewContributor implements EditionOverviewContributor
{
    public function __construct(
        private readonly EvaluationBoard $evaluation,
        private readonly WaitlistPromotions $promotions,
        private readonly CfpWindow $window,
        private readonly SpeakerProfiles $profiles,
    ) {}

    public function contribute(string $editionId): OverviewContribution
    {
        $settings = CfpSetting::query()->forEdition($editionId)->first();
        $rows = $this->evaluation->build($editionId)['rows'];
        $confirmations = SpeakerConfirmation::query()->forEdition($editionId)->get();
        $confirmed = $confirmations->where('status', ConfirmationStatus::Confirmed);
        $slots = (int) CfpTrack::query()->forEdition($editionId)->sum('slots');

        $withoutReviewer = count(array_filter($rows, fn (array $row): bool => $row['assigned'] === 0));
        $noResponse = $confirmations->where('status', ConfirmationStatus::NoResponse)->count();
        $openSlots = array_sum(array_column($this->promotions->suggestions($editionId), 'open_slots'));

        $pending = [];

        if ($settings === null || $settings->opens_at === null || $settings->closes_at === null) {
            $pending[] = $this->item('cfp_window', 'CFP sem janela de submissão definida', 'warn', 'Atenção', 'Configurar CFP', '/cfp', 1, EditionPermission::ManageEdition);
        }

        if ($withoutReviewer > 0) {
            $pending[] = $this->item('proposals_without_reviewer', sprintf('%d %s sem revisor', $withoutReviewer, $withoutReviewer === 1 ? 'proposta' : 'propostas'), 'warn', 'Atenção', 'Atribuir', '/cfp/avaliacao?filtro=sem-revisor', 2, EditionPermission::ManageProgram);
        }

        if ($noResponse > 0) {
            $pending[] = $this->item('speakers_no_response', sprintf('%d %s sem resposta à confirmação', $noResponse, $noResponse === 1 ? 'palestrante' : 'palestrantes'), 'warn', 'Atenção', 'Ver palestrantes', '/palestrantes?confirmacao=sem-resposta', 2, EditionPermission::ManageProgram);
        }

        if ($openSlots > 0) {
            $pending[] = $this->item('waitlist_promotions', sprintf('%d %s na grade para a lista de espera', $openSlots, $openSlots === 1 ? 'vaga liberada' : 'vagas liberadas'), 'info', 'Grade', 'Promover', '/cfp/avaliacao?aba=decisao', 3, EditionPermission::ManageProgram);
        }

        return new OverviewContribution(
            indicators: [
                'proposals' => [
                    'total' => count($rows),
                    'reviewed' => count(array_filter($rows, fn (array $row): bool => $row['review_status'] !== EvaluationBoard::PENDING)),
                ],
                'speakers' => ['confirmed' => $confirmed->count(), 'goal' => $slots],
            ],
            pending: $pending,
            milestones: $this->milestones($settings, $editionId),
            sections: ['nordeste' => $this->nordeste($editionId, $settings, array_values($confirmed->pluck('proposal_id')->all()))],
        );
    }

    /**
     * @return array{key: string, title: string, tone: string, tag: string, action_label: string, action_path: string, priority: int, permission: EditionPermission}
     */
    private function item(string $key, string $title, string $tone, string $tag, string $actionLabel, string $path, int $priority, EditionPermission $permission): array
    {
        return ['key' => $key, 'title' => $title, 'tone' => $tone, 'tag' => $tag, 'action_label' => $actionLabel, 'action_path' => $path, 'priority' => $priority, 'permission' => $permission];
    }

    /**
     * @return list<array{key: string, title: string, description: string, date: string}>
     */
    private function milestones(?CfpSetting $settings, string $editionId): array
    {
        if (!$settings instanceof CfpSetting) {
            return [];
        }

        $milestones = [];

        if ($settings->opens_at !== null && $this->window->status($editionId) !== CfpStatus::Open) {
            $milestones[] = ['key' => 'cfp_opens', 'title' => 'Abertura do CFP', 'description' => 'Propostas passam a ser aceitas', 'date' => $settings->opens_at->toDateString()];
        }

        if ($settings->closes_at !== null) {
            $milestones[] = ['key' => 'cfp_closes', 'title' => 'Fechamento do CFP', 'description' => 'Propostas deixam de ser aceitas', 'date' => $settings->closes_at->toDateString()];
        }

        if ($settings->results_at !== null) {
            $milestones[] = ['key' => 'cfp_results', 'title' => 'Resultado para palestrantes', 'description' => 'E-mails de aprovação e lista de espera', 'date' => $settings->results_at->toDateString()];
        }

        return $milestones;
    }

    /**
     * @param  list<mixed>  $confirmedProposalIds
     * @return array<string, mixed>
     */
    private function nordeste(string $editionId, ?CfpSetting $settings, array $confirmedProposalIds): array
    {
        $base = $confirmedProposalIds === [] ? 'accepted' : 'confirmed';
        $proposalIds = $confirmedProposalIds !== []
            ? $confirmedProposalIds
            : Proposal::query()->forEdition($editionId)->where('decision', ProposalDecision::Accepted)->pluck('id')->all();

        $people = array_values(array_unique(ProposalSpeaker::query()->forEdition($editionId)->whereIn('proposal_id', $proposalIds)->pluck('user_id')->map(fn (mixed $id): string => (string) $id)->all()));
        $coverage = CoverageCalculator::calculate($this->profiles->states($people));

        return [
            'base' => $base,
            'people' => $coverage['people'],
            'percent' => $coverage['nordeste_percent'],
            'quota' => $settings->nordeste_quota_percent ?? 50,
            'require_quota' => $settings->require_nordeste_quota ?? true,
            'missing_states' => array_map(fn (string $uf): array => ['uf' => $uf, 'name' => BrazilianState::from($uf)->label()], $coverage['nordeste_states_missing']),
        ];
    }
}
