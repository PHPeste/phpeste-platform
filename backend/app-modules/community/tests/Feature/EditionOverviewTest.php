<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use PHPeste\Branding\Models\EditionDomain;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Cfp\Models\CfpTrack;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\SpeakerConfirmation;
use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Community\Data\OverviewContribution;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\SpeakerProfile;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    $this->travelTo('2027-03-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::CfpOpen)->create(['name' => 'PHPeste 2027', 'starts_on' => '2027-10-08', 'ends_on' => '2027-10-09']);
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->url = "/api/v1/editions/{$this->edition->id}/overview";

    CfpSetting::create(['edition_id' => $this->edition->id, 'opens_at' => '2027-03-01', 'closes_at' => '2027-04-15', 'results_at' => '2027-04-30', 'nordeste_quota_percent' => 50]);
    $track = CfpTrack::create(['edition_id' => $this->edition->id, 'name' => 'Backend', 'slots' => 24, 'position' => 1]);

    foreach ([['PB', 'accepted', 'confirmed'], ['SP', 'accepted', 'confirmed'], ['CE', 'accepted', 'no_response'], ['RJ', null, null]] as $index => [$state, $decision, $confirmation]) {
        $author = User::factory()->create();
        SpeakerProfile::create(['user_id' => $author->id, 'display_name' => "Autor {$index}", 'state' => $state, 'links' => []]);
        $proposal = Proposal::create(['edition_id' => $this->edition->id, 'number' => $index + 1, 'track_id' => $track->id, 'title' => "P{$index}", 'status' => 'submitted', 'decision' => $decision]);
        $proposal->speakers()->create(['edition_id' => $this->edition->id, 'user_id' => $author->id, 'is_owner' => true]);

        if ($confirmation !== null) {
            SpeakerConfirmation::create(['edition_id' => $this->edition->id, 'proposal_id' => $proposal->id, 'status' => $confirmation, 'deadline_at' => now()->addWeek()]);
        }
    }

    Sanctum::actingAs($this->organizer);
});

it('agrega etapa, indicadores, pendências, Nordeste e marcos num endpoint só', function (): void {
    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.edition.name', 'PHPeste 2027')
        ->assertJsonPath('data.stage.current', 'cfp_open')
        ->assertJsonPath('data.stage.days_until_event', 212)
        ->assertJsonPath('data.stage.steps.0', ['status' => 'draft', 'label' => 'Rascunho', 'note' => 'Preparação', 'state' => 'done'])
        ->assertJsonPath('data.stage.steps.1.state', 'current')
        ->assertJsonPath('data.stage.steps.2.state', 'next')
        ->assertJsonPath('data.stage.steps.4.state', 'upcoming')
        ->assertJsonPath('data.indicators.proposals', ['total' => 4, 'reviewed' => 0])
        ->assertJsonPath('data.indicators.speakers', ['confirmed' => 2, 'goal' => 24])
        ->assertJsonPath('data.indicators.tickets', null)
        ->assertJsonPath('data.indicators.revenue', null)
        ->assertJsonPath('data.sections.tickets_per_week', null)
        ->assertJsonPath('data.sections.nordeste.base', 'confirmed')
        ->assertJsonPath('data.sections.nordeste.percent', 50)
        ->assertJsonPath('data.sections.nordeste.missing_states.0', ['uf' => 'AL', 'name' => 'Alagoas'])
        ->assertJsonPath('data.milestones.0', ['key' => 'cfp_closes', 'title' => 'Fechamento do CFP', 'description' => 'Propostas deixam de ser aceitas', 'date' => '2027-04-15'])
        ->assertJsonPath('data.milestones.1.key', 'cfp_results')
        ->assertJsonPath('data.milestones.2.key', 'event');
});

it('lista pendências priorizadas, cada uma com o caminho e o filtro da tela que resolve', function (): void {
    $pending = collect($this->getJson($this->url)->json('data.pending'));

    expect($pending->pluck('key')->all())->toBe(['proposals_without_reviewer', 'speakers_no_response', 'theme_unpublished', 'domain_missing'])
        ->and($pending->firstWhere('key', 'proposals_without_reviewer'))->toMatchArray(['title' => '4 propostas sem revisor', 'action_label' => 'Atribuir', 'action_path' => '/cfp/avaliacao?filtro=sem-revisor'])
        ->and($pending->firstWhere('key', 'speakers_no_response'))->toMatchArray(['title' => '1 palestrante sem resposta à confirmação', 'action_path' => '/palestrantes?confirmacao=sem-resposta'])
        ->and($pending->first())->not->toHaveKeys(['permission', 'priority']);
});

it('some a pendência quando ela é resolvida', function (): void {
    EditionDomain::create(['edition_id' => $this->edition->id, 'host' => '2027.phpeste.dev', 'verification_token' => 'x', 'verified_at' => now()]);

    expect(collect($this->getJson($this->url)->json('data.pending'))->pluck('key'))->not->toContain('domain_missing');
});

it('mostra a cada pessoa só as pendências que ela pode resolver', function (): void {
    $volunteer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $volunteer->id, 'role' => EditionRole::Volunteer]);
    Sanctum::actingAs($volunteer);

    $this->getJson($this->url)->assertOk()->assertJsonPath('data.pending', [])->assertJsonPath('data.indicators.speakers.confirmed', 2);
});

it('aceita contribuição de qualquer módulo registrado, como as vendas no futuro', function (): void {
    app()->tag([SalesContributorForTest::class], EditionOverviewContributor::TAG);

    $this->getJson($this->url)
        ->assertJsonPath('data.indicators.tickets', ['sold' => 128, 'available' => 400])
        ->assertJsonPath('data.sections.tickets_per_week', [12, 18])
        ->assertJsonPath('data.pending.0.key', 'webhook_untested');
});

it('nega a visão geral a quem não é da edição', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson($this->url)->assertForbidden();
});

class SalesContributorForTest implements EditionOverviewContributor
{
    public function contribute(string $editionId): OverviewContribution
    {
        return new OverviewContribution(
            indicators: ['tickets' => ['sold' => 128, 'available' => 400]],
            pending: [['key' => 'webhook_untested', 'title' => 'Webhook do gateway sem teste', 'tone' => 'danger', 'tag' => 'Bloqueia vendas', 'action_label' => 'Testar', 'action_path' => '/pagamentos', 'priority' => 0, 'permission' => EditionPermission::ManageGateway]],
            sections: ['tickets_per_week' => [12, 18]],
        );
    }
}
