<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Tickets;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use PHPeste\Ticketing\Models\Ticket;

class TicketOwnership
{
    /**
     * @return Builder<Ticket>
     */
    public function query(string $editionId, Authenticatable $user): Builder
    {
        $id = (string) $user->getAuthIdentifier();
        $email = $this->verifiedEmail($user);

        return Ticket::query()->forEdition($editionId)
            ->where(fn (Builder $query) => $query
                ->where('holder_user_id', $id)
                ->when($email !== null, fn (Builder $byEmail) => $byEmail->orWhere(fn (Builder $unclaimed) => $unclaimed->whereNull('holder_user_id')->where('holder_email', $email))));
    }

    public function claim(Ticket $ticket, Authenticatable $user): void
    {
        if ($ticket->holder_user_id === null) {
            Ticket::query()->forEdition($ticket->edition_id)->whereKey($ticket->id)->whereNull('holder_user_id')->update(['holder_user_id' => (string) $user->getAuthIdentifier()]);
        }
    }

    public function verifiedEmail(Authenticatable $user): ?string
    {
        $email = $user instanceof MustVerifyEmail && $user->hasVerifiedEmail() ? $user->getEmailForVerification() : null;

        return $email === null ? null : Str::lower($email);
    }
}
