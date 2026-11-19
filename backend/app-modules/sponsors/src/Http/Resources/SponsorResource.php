<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Sponsors\Http\People;
use PHPeste\Sponsors\Models\Sponsor;
use PHPeste\Sponsors\Models\SponsorAttachment;
use PHPeste\Sponsors\Models\SponsorContact;
use PHPeste\Sponsors\Models\Sponsorship;

class SponsorResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sponsor = $this->resource;

        if (!$sponsor instanceof Sponsor) {
            return [];
        }

        $people = resolve(People::class);

        return [
            'id' => $sponsor->id,
            'name' => $sponsor->name,
            'website' => $sponsor->website,
            'contact' => ['name' => $sponsor->contact_name, 'email' => $sponsor->contact_email, 'phone' => $sponsor->contact_phone],
            'notes' => $sponsor->notes,
            'created_at' => $this->isoDate($sponsor->created_at),
            ...($sponsor->relationLoaded('sponsorships') ? ['sponsorships' => $sponsor->sponsorships->map(fn (Sponsorship $deal): array => new SponsorshipResource($deal)->toArray($request))->all()] : []),
            ...($sponsor->relationLoaded('contacts') ? ['contacts' => $sponsor->contacts->map(fn (SponsorContact $contact): array => [
                'id' => $contact->id,
                'channel' => $contact->channel->value,
                'channel_label' => $contact->channel->label(),
                'happened_at' => $this->isoDate($contact->happened_at),
                'summary' => $contact->summary,
                'author' => $people->get($contact->user_id),
            ])->all()] : []),
            ...($sponsor->relationLoaded('attachments') ? ['attachments' => $sponsor->attachments->map(fn (SponsorAttachment $attachment): array => SponsorAttachmentResource::present($attachment, $people))->all()] : []),
        ];
    }
}
