<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Notifications\Models\EmailDelivery;
use PHPeste\Sponsors\Models\Sponsor;
use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Sponsors\Models\SponsorTier;
use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Suppliers\Models\Supplier;
use PHPeste\Tasks\Cloning\TasksCloner;
use PHPeste\Tasks\Models\Task;
use PHPeste\Tasks\Models\TaskColumn;

beforeEach(function (): void {
    $this->travelTo('2027-06-10 09:00:00');
    $this->edition = Edition::factory()->create(['name' => 'PHPeste 2027']);
    $this->organizer = User::factory()->create(['name' => 'Rita Organiza']);
    $this->volunteer = User::factory()->create(['name' => 'Vini Voluntário', 'email' => 'vini@exemplo.com']);
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->volunteer->id, 'role' => EditionRole::Volunteer]);
    $this->api = "/api/v1/editions/{$this->edition->id}/tasks";
    Sanctum::actingAs($this->organizer);
});

function board(object $test, string $filter = 'all'): array
{
    return $test->getJson("{$test->api}/board?filter={$filter}")->assertOk()->json('data');
}

function newTask(object $test, array $attributes = []): array
{
    return $test->postJson($test->api, ['title' => 'Fechar o buffet', ...$attributes])->assertCreated()->json('data');
}

function titles(array $board, int $column): array
{
    return array_column($board['columns'][$column]['tasks'], 'title');
}

it('o quadro nasce com quatro colunas e a tarefa entra no fim da primeira', function (): void {
    $empty = board($this);

    expect(array_column($empty['columns'], 'name'))->toBe(['A fazer', 'Fazendo', 'Aguardando', 'Feito'])
        ->and(array_column($empty['columns'], 'is_done'))->toBe([false, false, false, true])
        ->and($empty['can_manage'])->toBeTrue();

    newTask($this, ['title' => 'Primeira']);
    $second = newTask($this, ['title' => 'Segunda', 'description' => 'Detalhes', 'due_on' => '2027-06-20', 'assignee_id' => $this->volunteer->id]);

    expect($second)->toMatchArray(['position' => 1, 'due_on' => '2027-06-20', 'overdue' => false, 'done' => false])
        ->and($second['assignee'])->toBe(['id' => $this->volunteer->id, 'name' => 'Vini Voluntário']);

    $full = board($this);
    expect(titles($full, 0))->toBe(['Primeira', 'Segunda'])->and($full['columns'][0]['count'])->toBe(2);
});

it('move entre colunas e dentro da coluna, sempre renumerando de 0 a n−1', function (): void {
    $cols = array_column(board($this)['columns'], 'id');
    $a = newTask($this, ['title' => 'A']);
    newTask($this, ['title' => 'B']);
    $c = newTask($this, ['title' => 'C']);

    $this->postJson("{$this->api}/{$c['id']}/move", ['task_column_id' => $cols[0], 'position' => 0])->assertOk();
    expect(titles(board($this), 0))->toBe(['C', 'A', 'B']);

    $moved = $this->postJson("{$this->api}/{$a['id']}/move", ['task_column_id' => $cols[3], 'position' => 99])->assertOk()->json('data');

    expect($moved['task'])->toMatchArray(['column_id' => $cols[3], 'position' => 0, 'done' => true])
        ->and(collect($moved['columns'])->firstWhere('id', $cols[0])['task_ids'])->toHaveCount(2);

    $after = board($this);
    expect(titles($after, 0))->toBe(['C', 'B'])
        ->and(array_column($after['columns'][0]['tasks'], 'position'))->toBe([0, 1])
        ->and(titles($after, 3))->toBe(['A']);

    $this->postJson("{$this->api}/{$a['id']}/move", ['task_column_id' => $cols[1], 'position' => 0])->assertOk()->assertJsonPath('data.task.done', false);

    $this->deleteJson("{$this->api}/{$c['id']}")->assertNoContent();
    expect(array_column(board($this)['columns'][0]['tasks'], 'position'))->toBe([0]);
});

it('tarefa com prazo vencido fica marcada como atrasada, até ir para a coluna de concluídas', function (): void {
    $cols = array_column(board($this)['columns'], 'id');
    $late = newTask($this, ['title' => 'Atrasada', 'due_on' => '2027-06-01', 'assignee_id' => $this->volunteer->id]);
    newTask($this, ['title' => 'No prazo', 'due_on' => '2027-07-01']);
    newTask($this, ['title' => 'Sem prazo']);

    expect($late['overdue'])->toBeTrue();

    $overdue = board($this, 'overdue');
    expect($overdue['totals'])->toBe(['tasks' => 1, 'overdue' => 1])->and(titles($overdue, 0))->toBe(['Atrasada']);

    $pending = collect($this->getJson("/api/v1/editions/{$this->edition->id}/overview")->json('data.pending'))->firstWhere('key', 'tasks_overdue');
    expect($pending['title'])->toBe('1 tarefa atrasada');

    $this->postJson("{$this->api}/{$late['id']}/move", ['task_column_id' => $cols[3], 'position' => 0])->assertOk()->assertJsonPath('data.task.overdue', false);
    expect(board($this, 'overdue')['totals']['overdue'])->toBe(0);
});

it('filtro "minhas" mostra só as tarefas da pessoa', function (): void {
    newTask($this, ['title' => 'Minha', 'assignee_id' => $this->organizer->id]);
    newTask($this, ['title' => 'Do Vini', 'assignee_id' => $this->volunteer->id]);

    expect(titles(board($this, 'mine'), 0))->toBe(['Minha']);

    Sanctum::actingAs($this->volunteer);
    expect(titles(board($this, 'mine'), 0))->toBe(['Do Vini']);
});

it('a equipe vê o quadro; o responsável move só a própria tarefa; o resto é de quem organiza', function (): void {
    $cols = array_column(board($this)['columns'], 'id');
    $mine = newTask($this, ['title' => 'Do Vini', 'assignee_id' => $this->volunteer->id]);
    $other = newTask($this, ['title' => 'Da Rita', 'assignee_id' => $this->organizer->id]);
    Sanctum::actingAs($this->volunteer);

    expect(board($this)['can_manage'])->toBeFalse();

    $this->postJson("{$this->api}/{$mine['id']}/move", ['task_column_id' => $cols[1], 'position' => 0])->assertOk();
    $this->postJson("{$this->api}/{$other['id']}/move", ['task_column_id' => $cols[1], 'position' => 0])->assertForbidden();
    $this->postJson($this->api, ['title' => 'Nova'])->assertForbidden();
    $this->patchJson("{$this->api}/{$mine['id']}", ['title' => 'Outro nome'])->assertForbidden();
    $this->deleteJson("{$this->api}/{$mine['id']}")->assertForbidden();
    $this->postJson("{$this->api}/columns", ['name' => 'X'])->assertForbidden();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("{$this->api}/board")->assertForbidden();
});

it('responsável precisa ser da equipe e recebe e-mail ao ser atribuído, se não desligou', function (): void {
    $this->postJson($this->api, ['title' => 'X', 'assignee_id' => User::factory()->create()->id])
        ->assertUnprocessable()->assertJsonPath('errors.assignee_id.0', 'O responsável precisa ser da equipe desta edição.');

    $task = newTask($this, ['title' => 'Fechar o buffet', 'due_on' => '2027-06-20', 'assignee_id' => $this->volunteer->id]);

    $mail = EmailDelivery::query()->forEdition($this->edition->id)->where('template', EmailTemplate::TaskAssigned->value)->sole();
    expect($mail->to_email)->toBe('vini@exemplo.com')
        ->and($mail->action_url)->toEndWith('/tarefas?tarefa='.$task['id']);

    newTask($this, ['title' => 'Eu mesma', 'assignee_id' => $this->organizer->id]);
    $this->volunteer->update(['notification_preferences' => ['assigned_tasks' => false]]);
    newTask($this, ['title' => 'Outra para o Vini', 'assignee_id' => $this->volunteer->id]);

    expect(EmailDelivery::query()->forEdition($this->edition->id)->where('template', EmailTemplate::TaskAssigned->value)->count())->toBe(1);
});

it('avisa o responsável um dia antes do prazo, uma vez só, e só ele', function (): void {
    $cols = array_column(board($this)['columns'], 'id');
    $tomorrow = newTask($this, ['title' => 'Vence amanhã', 'due_on' => '2027-06-11', 'assignee_id' => $this->volunteer->id]);
    newTask($this, ['title' => 'Sem responsável', 'due_on' => '2027-06-11']);
    newTask($this, ['title' => 'Daqui a uma semana', 'due_on' => '2027-06-17', 'assignee_id' => $this->volunteer->id]);
    $done = newTask($this, ['title' => 'Já feita', 'due_on' => '2027-06-11', 'assignee_id' => $this->organizer->id]);
    $this->postJson("{$this->api}/{$done['id']}/move", ['task_column_id' => $cols[3], 'position' => 0])->assertOk();
    EmailDelivery::query()->forEdition($this->edition->id)->delete();

    $this->artisan('tasks:due-reminders')->assertSuccessful();
    $this->artisan('tasks:due-reminders')->assertSuccessful();

    $reminders = EmailDelivery::query()->forEdition($this->edition->id)->where('template', EmailTemplate::TaskDueSoon->value)->get();
    expect($reminders)->toHaveCount(1)
        ->and($reminders->first()->to_email)->toBe('vini@exemplo.com')
        ->and($reminders->first()->variables)->toMatchArray(['tarefa' => 'Vence amanhã', 'prazo' => '11 de junho de 2027']);

    $this->patchJson("{$this->api}/{$tomorrow['id']}", ['due_on' => '2027-06-10'])->assertOk();
    $this->artisan('tasks:due-reminders')->assertSuccessful();

    expect(EmailDelivery::query()->forEdition($this->edition->id)->where('template', EmailTemplate::TaskDueSoon->value)->count())->toBe(2);
});

it('liga a tarefa a um fornecedor ou patrocinador da edição', function (): void {
    $supplier = Supplier::create(['community_id' => $this->edition->community_id, 'name' => 'Buffet Sertão', 'name_key' => 'buffet sertao', 'service' => 'catering']);
    $link = EditionSupplier::create(['edition_id' => $this->edition->id, 'supplier_id' => $supplier->id, 'status' => 'quoting']);
    $sponsor = Sponsor::create(['edition_id' => $this->edition->id, 'name' => 'Acme']);
    $tier = SponsorTier::create(['edition_id' => $this->edition->id, 'name' => 'Ouro', 'price_cents' => 1, 'benefits' => []]);
    $deal = Sponsorship::create(['edition_id' => $this->edition->id, 'sponsor_id' => $sponsor->id, 'sponsor_tier_id' => $tier->id, 'stage' => 'closed', 'value_cents' => 1]);

    $options = collect($this->getJson("{$this->api}/link-options")->assertOk()->json('data'))->keyBy('type');
    expect($options['supplier']['options'])->toBe([['id' => $link->id, 'name' => 'Buffet Sertão']])
        ->and($options['sponsorship']['options'])->toBe([['id' => $deal->id, 'name' => 'Acme (Ouro)']]);

    $task = newTask($this, ['title' => 'Pedir orçamento final', 'link_type' => 'supplier', 'link_id' => $link->id]);
    expect($task['link'])->toBe(['type' => 'supplier', 'type_label' => 'Fornecedor', 'id' => $link->id, 'name' => 'Buffet Sertão']);

    $this->patchJson("{$this->api}/{$task['id']}", ['link_type' => 'sponsorship', 'link_id' => $deal->id])->assertOk()->assertJsonPath('data.link.name', 'Acme (Ouro)');

    $other = Edition::factory()->create();
    $foreign = Sponsorship::create(['edition_id' => $other->id, 'sponsor_id' => Sponsor::create(['edition_id' => $other->id, 'name' => 'Beta'])->id, 'stage' => 'closed', 'value_cents' => 1]);
    $this->postJson($this->api, ['title' => 'X', 'link_type' => 'sponsorship', 'link_id' => $foreign->id])->assertUnprocessable()->assertJsonValidationErrors(['link_id']);
    $this->postJson($this->api, ['title' => 'X', 'link_type' => 'sessao', 'link_id' => $deal->id])->assertUnprocessable()->assertJsonValidationErrors(['link_type']);
});

it('colunas: cria, renomeia, reordena e só remove vazia, sem ficar sem coluna de concluídas', function (): void {
    $cols = board($this)['columns'];
    $new = $this->postJson("{$this->api}/columns", ['name' => 'Bloqueado'])->assertCreated()->json('data');

    expect($new['position'])->toBe(4);

    $order = [$cols[3]['id'], $new['id'], $cols[0]['id'], $cols[1]['id'], $cols[2]['id']];
    $this->postJson("{$this->api}/columns/reorder", ['ids' => $order])->assertOk()->assertJsonPath('data.0.name', 'Feito');
    $this->postJson("{$this->api}/columns/reorder", ['ids' => array_slice($order, 1)])->assertUnprocessable();

    $this->patchJson("{$this->api}/columns/{$cols[3]['id']}", ['is_done' => false])->assertUnprocessable()->assertJsonValidationErrors(['is_done']);
    $this->deleteJson("{$this->api}/columns/{$cols[3]['id']}")->assertUnprocessable();
    $this->patchJson("{$this->api}/columns/{$cols[0]['id']}", ['name' => 'Backlog'])->assertOk()->assertJsonPath('data.name', 'Backlog');

    newTask($this, ['task_column_id' => $new['id']]);
    $this->deleteJson("{$this->api}/columns/{$new['id']}")->assertUnprocessable()->assertJsonValidationErrors(['column']);
    $this->deleteJson("{$this->api}/columns/{$cols[2]['id']}")->assertNoContent();

    expect(array_column(board($this)['columns'], 'position'))->toBe([0, 1, 2, 3]);
});

it('a próxima edição recebe o quadro como checklist, sem responsáveis nem prazos', function (): void {
    $cols = array_column(board($this)['columns'], 'id');
    newTask($this, ['title' => 'Reservar auditório', 'assignee_id' => $this->volunteer->id, 'due_on' => '2027-03-01']);
    $done = newTask($this, ['title' => 'Abrir o CFP']);
    $this->postJson("{$this->api}/{$done['id']}/move", ['task_column_id' => $cols[3], 'position' => 0])->assertOk();
    $next = Edition::factory()->create();

    resolve(TasksCloner::class)->clone($this->edition->id, $next->id);

    $first = TaskColumn::query()->forEdition($next->id)->orderBy('position')->first();
    $copied = Task::query()->forEdition($next->id)->orderBy('position')->get();

    expect(TaskColumn::query()->forEdition($next->id)->count())->toBe(4)
        ->and($copied->pluck('title')->all())->toBe(['Reservar auditório', 'Abrir o CFP'])
        ->and($copied->every(fn (Task $task): bool => $task->task_column_id === $first->id && $task->assignee_id === null && $task->due_on === null && $task->completed_at === null))->toBeTrue();
});
