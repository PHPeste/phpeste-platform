<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionInvitation;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Notifications\Mail\EditionMail;

beforeEach(function (): void {
    Mail::fake();
    config(['app.admin_url' => 'https://admin.phpeste.dev']);
    $this->edition = Edition::factory()->create(['name' => 'PHPeste 2027']);
    $this->organizer = User::factory()->create(['name' => 'Mariana Sales']);
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->url = "/api/v1/editions/{$this->edition->id}/team/invitations";
});

function inviteAndGrabToken(object $test, string $email, string $role = 'financeiro'): string
{
    Sanctum::actingAs($test->organizer);
    $test->postJson($test->url, ['email' => $email, 'role' => $role])->assertCreated();

    $token = null;

    Mail::assertSent(EditionMail::class, function (EditionMail $mail) use ($email, &$token): bool {
        if (!$mail->hasTo($email) || preg_match('#https://admin\.phpeste\.dev/convite/([A-Za-z0-9]+)#', $mail->email->html, $match) !== 1) {
            return false;
        }

        $token = $match[1];

        return true;
    });

    resolve('auth')->forgetGuards();

    return (string) $token;
}

it('convida por e-mail com link para o painel e registra na auditoria', function (): void {
    Sanctum::actingAs($this->organizer);

    $this->postJson($this->url, ['email' => 'Livia@Example.com', 'name' => 'Lívia Ferraz', 'role' => 'financeiro'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'livia@example.com')
        ->assertJsonPath('data.role_label', 'Financeiro')
        ->assertJsonMissingPath('data.token_hash');

    Mail::assertSent(EditionMail::class, fn (EditionMail $mail): bool => $mail->hasTo('livia@example.com')
        && $mail->email->subject === 'Convite para a equipe do PHPeste 2027'
        && str_contains($mail->email->html, 'com o papel de financeiro')
        && str_contains($mail->email->html, 'https://admin.phpeste.dev/convite/'));

    $this->assertDatabaseHas('audit_entries', ['action' => 'team.invited', 'actor_id' => $this->organizer->id]);
});

it('não convida quem já está na equipe', function (): void {
    Sanctum::actingAs($this->organizer);
    $member = User::factory()->create(['email' => 'joao@example.com']);
    EditionMember::factory()->for($this->edition)->create(['user_id' => $member->id, 'role' => EditionRole::Reviewer]);

    $this->postJson($this->url, ['email' => 'JOAO@example.com', 'role' => 'financeiro'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('mostra os dados públicos do convite', function (): void {
    $token = inviteAndGrabToken($this, 'livia@example.com');

    $this->getJson("/api/v1/invitations/{$token}")
        ->assertOk()
        ->assertJsonPath('data.edition.name', 'PHPeste 2027')
        ->assertJsonPath('data.invited_by', 'Mariana Sales')
        ->assertJsonPath('data.role_label', 'Financeiro')
        ->assertJsonPath('data.email', 'livia@example.com')
        ->assertJsonPath('data.account_exists', false);
});

it('aceita criando a conta já verificada, entra na equipe e recebe token', function (): void {
    $token = inviteAndGrabToken($this, 'livia@example.com');

    $response = $this->postJson("/api/v1/invitations/{$token}/accept", [
        'name' => 'Lívia Ferraz',
        'password' => 'umasenhabemforte123',
        'password_confirmation' => 'umasenhabemforte123',
    ])->assertOk()
        ->assertJsonPath('user.email', 'livia@example.com')
        ->assertJsonPath('edition_id', $this->edition->id)
        ->assertJsonStructure(['token']);

    $user = User::where('email', 'livia@example.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeTrue();
    $this->assertDatabaseHas('edition_members', ['edition_id' => $this->edition->id, 'user_id' => $user->id, 'role' => 'financeiro']);
    $this->assertDatabaseHas('audit_entries', ['action' => 'team.joined', 'actor_id' => $user->id]);

    $this->withToken($response->json('token'))->getJson('/api/v1/me')->assertJsonPath('roles.0.role', 'financeiro');
});

it('exige senha forte e confirmada para criar a conta', function (): void {
    $token = inviteAndGrabToken($this, 'livia@example.com');

    $this->postJson("/api/v1/invitations/{$token}/accept", ['name' => 'Lívia', 'password' => '123'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    $this->assertDatabaseMissing('users', ['email' => 'livia@example.com']);
});

it('vincula a conta existente conferindo a senha', function (): void {
    $existing = User::factory()->create(['email' => 'livia@example.com', 'password' => 'umasenhabemforte123']);
    $token = inviteAndGrabToken($this, 'livia@example.com');

    $this->getJson("/api/v1/invitations/{$token}")->assertJsonPath('data.account_exists', true);

    $this->postJson("/api/v1/invitations/{$token}/accept", ['password' => 'errada'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    $this->postJson("/api/v1/invitations/{$token}/accept", ['password' => 'umasenhabemforte123'])
        ->assertOk()
        ->assertJsonPath('user.id', $existing->id);

    expect(User::where('email', 'livia@example.com')->count())->toBe(1);
});

it('aceita sem senha quando a pessoa já está logada com o e-mail do convite, e recusa outra conta', function (): void {
    $existing = User::factory()->create(['email' => 'livia@example.com']);
    $token = inviteAndGrabToken($this, 'livia@example.com');

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/invitations/{$token}/accept")->assertForbidden();

    Sanctum::actingAs($existing);
    $this->postJson("/api/v1/invitations/{$token}/accept")
        ->assertOk()
        ->assertJsonMissingPath('token');
});

it('rejeita convite já usado', function (): void {
    $token = inviteAndGrabToken($this, 'livia@example.com');
    $payload = ['name' => 'Lívia', 'password' => 'umasenhabemforte123', 'password_confirmation' => 'umasenhabemforte123'];

    $this->postJson("/api/v1/invitations/{$token}/accept", $payload)->assertOk();

    $this->postJson("/api/v1/invitations/{$token}/accept", $payload)
        ->assertStatus(410)
        ->assertJsonPath('code', 'gone')
        ->assertJsonPath('message', 'Este convite já foi usado.');
    $this->getJson("/api/v1/invitations/{$token}")->assertStatus(410);
});

it('rejeita convite expirado', function (): void {
    EditionInvitation::factory()->for($this->edition)->expired()->withToken('tokenexpirado')->create();

    $this->getJson('/api/v1/invitations/tokenexpirado')
        ->assertStatus(410)
        ->assertJsonPath('message', 'Este convite expirou. Peça um novo para a organização.');
    $this->postJson('/api/v1/invitations/tokenexpirado/accept', ['password' => 'x'])->assertStatus(410);
});

it('cancela o convite e o link deixa de valer', function (): void {
    $token = inviteAndGrabToken($this, 'livia@example.com');
    $invitation = EditionInvitation::query()->where('email', 'livia@example.com')->firstOrFail();

    Sanctum::actingAs($this->organizer);
    $this->deleteJson("{$this->url}/{$invitation->id}")->assertNoContent();

    $this->getJson("/api/v1/invitations/{$token}")->assertStatus(410)->assertJsonPath('message', 'Este convite foi cancelado.');
    $this->assertDatabaseHas('audit_entries', ['action' => 'team.invitation_revoked', 'subject_id' => $invitation->id]);
});

it('reenviar gera link novo e invalida o antigo; convidar de novo cancela o pendente', function (): void {
    $first = inviteAndGrabToken($this, 'livia@example.com');
    $invitation = EditionInvitation::query()->where('email', 'livia@example.com')->firstOrFail();

    Sanctum::actingAs($this->organizer);
    $this->postJson("{$this->url}/{$invitation->id}/resend")->assertOk();
    $this->getJson("/api/v1/invitations/{$first}")->assertNotFound();

    $this->postJson($this->url, ['email' => 'livia@example.com', 'role' => 'voluntario'])->assertCreated();

    expect(EditionInvitation::query()->where('email', 'livia@example.com')->pending()->count())->toBe(1)
        ->and(EditionInvitation::query()->where('email', 'livia@example.com')->pending()->first()?->role)->toBe(EditionRole::Volunteer);
});

it('responde 404 para token desconhecido', function (): void {
    $this->getJson('/api/v1/invitations/naoexiste')->assertNotFound();
});
