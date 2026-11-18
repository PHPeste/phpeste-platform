<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Community\Events\TeamChanged;
use PHPeste\Community\Models\EditionInvitation;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Contracts\UserAccounts;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

class AcceptInvitation
{
    public function __construct(private readonly UserAccounts $accounts) {}

    public static function findUsable(string $token): EditionInvitation
    {
        $invitation = EditionInvitation::query()->where('token_hash', EditionInvitation::hashToken($token))->first();

        abort_if($invitation === null, 404, 'Convite não encontrado.');

        throw_if($invitation->accepted_at !== null, GoneHttpException::class, 'Este convite já foi usado.');

        throw_if($invitation->revoked_at !== null, GoneHttpException::class, 'Este convite foi cancelado.');

        throw_if($invitation->expires_at->isPast(), GoneHttpException::class, 'Este convite expirou. Peça um novo para a organização.');

        return $invitation;
    }

    /**
     * @return array{id: string, name: string, email: string}
     */
    public function handle(string $token, ?string $authenticatedUserId, ?string $name, ?string $password): array
    {
        $invitation = self::findUsable($token);
        $account = $this->accounts->findByEmail($invitation->email);

        if ($authenticatedUserId !== null) {
            throw_if($account === null || $account['id'] !== $authenticatedUserId, AccessDeniedHttpException::class, 'Este convite foi enviado para outro e-mail.');
        } elseif ($account !== null) {
            if ($password === null || !$this->accounts->checkPassword($account['id'], $password)) {
                throw ValidationException::withMessages(['password' => 'Senha incorreta para a conta deste e-mail.']);
            }
        } else {
            if ($name === null || $password === null) {
                throw ValidationException::withMessages(['name' => 'Informe nome e senha para criar a conta.']);
            }

            $account = $this->accounts->registerVerified($name, $invitation->email, $password);
        }

        DB::transaction(function () use ($invitation, $account): void {
            $member = EditionMember::query()->firstOrCreate(
                ['edition_id' => $invitation->edition_id, 'user_id' => $account['id']],
                ['role' => $invitation->role],
            );

            $invitation->update(['accepted_at' => now(), 'accepted_by' => $account['id']]);

            event(new TeamChanged($invitation->edition_id, TeamChanged::JOINED, $account['id'], 'member', $member->id, [
                'user_id' => $account['id'],
                'role' => $member->role->value,
                'invitation_id' => $invitation->id,
            ]));
        });

        return $account;
    }
}
