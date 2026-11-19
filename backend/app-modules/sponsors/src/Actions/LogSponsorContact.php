<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Actions;

use Illuminate\Support\Carbon;
use PHPeste\Sponsors\Enums\ContactChannel;
use PHPeste\Sponsors\Models\Sponsor;
use PHPeste\Sponsors\Models\SponsorContact;

class LogSponsorContact
{
    public function handle(Sponsor $sponsor, ContactChannel $channel, Carbon $happenedAt, string $summary, ?string $actorId): SponsorContact
    {
        return SponsorContact::create([
            'edition_id' => $sponsor->edition_id,
            'sponsor_id' => $sponsor->id,
            'user_id' => $actorId,
            'channel' => $channel,
            'happened_at' => $happenedAt,
            'summary' => $summary,
        ]);
    }
}
