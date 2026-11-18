<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;
use PHPeste\Audit\Models\AuditLog;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    $this->edition = Edition::factory()->create();
    $this->organizer = User::factory()->create(['name' => 'Rita Organizadora']);
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->volunteer = User::factory()->create();
    $this->volunteerMember = EditionMember::factory()->for($this->edition)->create(['user_id' => $this->volunteer->id, 'role' => EditionRole::Volunteer]);
    $this->url = "/api/v1/editions/{$this->edition->id}/audit";
    Sanctum::actingAs($this->organizer);
});

it('registra a troca de papel com quem, antes, depois, IP e request_id', function (): void {
    $this->withHeader('X-Request-Id', 'req-troca-de-papel')
        ->withServerVariables(['REMOTE_ADDR' => '200.100.50.25'])
        ->patchJson("/api/v1/editions/{$this->edition->id}/team/members/{$this->volunteerMember->id}", ['role' => 'financeiro'])
        ->assertOk();

    $log = AuditLog::query()->where('action', 'team.role_changed')->firstOrFail();

    expect($log->actor_id)->toBe($this->organizer->id)
        ->and($log->edition_id)->toBe($this->edition->id)
        ->and($log->after)->toMatchArray(['from' => 'voluntario', 'to' => 'financeiro'])
        ->and($log->ip)->toBe('200.100.50.25')
        ->and($log->request_id)->toBe('req-troca-de-papel');
});

it('qualquer evento auditável vira registro, com segredos removidos', function (): void {
    event(new readonly class($this->edition->id, $this->organizer->id) implements Auditable
    {
        public function __construct(private string $editionId, private string $actorId) {}

        public function auditRecord(): AuditRecord
        {
            return new AuditRecord($this->editionId, 'payments.gateway_updated', 'gateway', $this->editionId, $this->actorId,
                ['provider' => 'asaas', 'api_key' => '$aact_antiga_123'],
                ['provider' => 'asaas', 'api_key' => '$aact_nova_456', 'contact_email' => 'financeiro@phpeste.dev'],
            );
        }
    });

    $log = AuditLog::query()->where('action', 'payments.gateway_updated')->firstOrFail();

    expect($log->before)->toBe(['provider' => 'asaas', 'api_key' => '[removido]'])
        ->and($log->after)->toBe(['provider' => 'asaas', 'api_key' => '[removido]', 'contact_email' => 'f***@phpeste.dev'])
        ->and((string) json_encode(DB::table('audit_logs')->get()))->not->toContain('aact_');
});

it('consulta com filtros por pessoa, ação e período, mostrando o nome de quem fez', function (): void {
    $this->travelTo('2027-03-01 10:00:00');
    $this->patchJson("/api/v1/editions/{$this->edition->id}/team/members/{$this->volunteerMember->id}", ['role' => 'financeiro']);
    $this->travelTo('2027-03-05 10:00:00');
    $this->postJson("/api/v1/editions/{$this->edition->id}/status", ['status' => 'cfp_open']);

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.action', 'edition.status_changed')
        ->assertJsonPath('data.0.before', ['status' => 'draft'])
        ->assertJsonPath('data.0.actor.name', 'Rita Organizadora');

    $this->getJson("{$this->url}?action=team.role_changed")->assertJsonPath('meta.total', 1);
    $this->getJson("{$this->url}?actor_id={$this->volunteer->id}")->assertJsonPath('meta.total', 0);
    $this->getJson("{$this->url}?from=2027-03-02&to=2027-03-06")->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.action', 'edition.status_changed');
    $this->getJson("{$this->url}?from=2027-03-06&to=2027-03-01")->assertStatus(422);
});

it('cada edição só vê a própria trilha, e só quem configura a edição consulta', function (): void {
    $other = Edition::factory()->create();
    EditionMember::factory()->for($other)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->patchJson("/api/v1/editions/{$this->edition->id}/team/members/{$this->volunteerMember->id}", ['role' => 'financeiro']);

    $this->getJson("/api/v1/editions/{$other->id}/audit")->assertOk()->assertJsonPath('meta.total', 0);

    Sanctum::actingAs($this->volunteer);
    $this->getJson($this->url)->assertForbidden();
});

it('registros não podem ser editados nem apagados pela API nem pelo model', function (): void {
    $this->patchJson("/api/v1/editions/{$this->edition->id}/team/members/{$this->volunteerMember->id}", ['role' => 'financeiro']);
    $log = AuditLog::query()->firstOrFail();

    $this->putJson("{$this->url}/{$log->id}", ['action' => 'nada'])->assertStatus(404);
    $this->deleteJson("{$this->url}/{$log->id}")->assertStatus(404);
    $this->deleteJson($this->url)->assertStatus(405);

    $routes = collect(Route::getRoutes())->filter(fn ($route): bool => str_contains($route->uri(), 'audit'));
    expect($routes->flatMap(fn ($route) => $route->methods())->unique()->sort()->values()->all())->toBe(['GET', 'HEAD']);

    expect(fn () => $log->update(['action' => 'adulterado']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
    expect(AuditLog::query()->firstOrFail()->action)->toBe('team.role_changed');
});

it('expurga só o que passou da retenção configurada', function (): void {
    config(['app.audit_retention_days' => 30]);
    $this->travelTo('2027-01-01 10:00:00');
    $this->patchJson("/api/v1/editions/{$this->edition->id}/team/members/{$this->volunteerMember->id}", ['role' => 'financeiro']);
    $this->travelTo('2027-02-20 10:00:00');
    $this->patchJson("/api/v1/editions/{$this->edition->id}/team/members/{$this->volunteerMember->id}", ['role' => 'voluntario']);

    $this->artisan('audit:prune')->expectsOutputToContain('1 registros com mais de 30 dias apagados.')->assertSuccessful();
    expect(AuditLog::query()->count())->toBe(1);

    config(['app.audit_retention_days' => 0]);
    $this->travelTo('2030-01-01');
    $this->artisan('audit:prune')->assertSuccessful();
    expect(AuditLog::query()->count())->toBe(1);
});

it('o expurgo está agendado todo dia', function (): void {
    $events = collect(resolve(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'audit:prune'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('30 3 * * *');
});
