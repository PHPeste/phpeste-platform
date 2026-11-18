<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Events\TeamChanged;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionInvitation;
use PHPeste\Identity\Contracts\UserAccounts;
use PHPeste\Notifications\Contracts\EditionMailer;
use PHPeste\Notifications\Enums\EmailTemplate;

class InviteTeamMember
{
    public const int VALID_FOR_DAYS = 7;

    public function __construct(
        private readonly UserAccounts $accounts,
        private readonly EditionMailer $mailer,
    ) {}

    public function handle(Edition $edition, string $email, EditionRole $role, ?string $name, string $invitedBy): EditionInvitation
    {
        $email = mb_strtolower(mb_trim($email));
        $account = $this->accounts->findByEmail($email);

        if ($account !== null && $edition->roleOf($account['id']) instanceof EditionRole) {
            throw ValidationException::withMessages([
                'email' => 'Esta pessoa já faz parte da equipe. Para mudar o que ela pode fazer, troque o papel.',
            ]);
        }

        $token = Str::random(48);

        $invitation = DB::transaction(function () use ($edition, $email, $role, $name, $invitedBy, $token): EditionInvitation {
            EditionInvitation::query()
                ->where('edition_id', $edition->id)
                ->where('email', $email)
                ->pending()
                ->update(['revoked_at' => now()]);

            $invitation = EditionInvitation::create([
                'edition_id' => $edition->id,
                'email' => $email,
                'name' => $name,
                'role' => $role,
                'token_hash' => EditionInvitation::hashToken($token),
                'invited_by' => $invitedBy,
                'expires_at' => now()->addDays(self::VALID_FOR_DAYS),
            ]);

            event(new TeamChanged($edition->id, TeamChanged::INVITED, $invitedBy, 'invitation', $invitation->id, [
                'email' => $email,
                'role' => $role->value,
            ]));

            return $invitation;
        });

        $this->send($invitation, $token, $account['name'] ?? $name);

        return $invitation;
    }

    public function send(EditionInvitation $invitation, string $token, ?string $name): void
    {
        $this->mailer->send(
            $invitation->edition_id,
            EmailTemplate::TeamInvite,
            $invitation->email,
            $name ?? $invitation->email,
            ['nome' => $name ?? $invitation->email, 'papel' => mb_strtolower($invitation->role->label())],
            mb_rtrim((string) config('app.admin_url'), '/').'/convite/'.$token,
        );
    }
}
