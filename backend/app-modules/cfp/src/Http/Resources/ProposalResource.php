<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Cfp\Models\Proposal;

class ProposalResource extends ApiResource
{
    /**
     * @param  list<array<string, mixed>>|null  $speakers
     */
    public function __construct(Proposal $proposal, private readonly ?array $speakers, private readonly bool $nordeste = false)
    {
        parent::__construct($proposal);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $proposal = $this->resource;

        if (!$proposal instanceof Proposal) {
            return [];
        }

        return [
            'id' => $proposal->id,
            'number' => $proposal->number,
            'title' => $proposal->title,
            'track' => $proposal->track === null ? null : ['id' => $proposal->track->id, 'name' => $proposal->track->name],
            'summary' => $proposal->summary,
            'audience' => $proposal->audience,
            'format' => $proposal->format?->value,
            'format_label' => $proposal->format?->label(),
            'duration_minutes' => $proposal->format?->minutes(),
            'level' => $proposal->level?->value,
            'level_label' => $proposal->level?->label(),
            'language' => $proposal->language->value,
            'status' => $proposal->status->value,
            'status_label' => $proposal->status->label(),
            'decision' => $proposal->decision?->value,
            'decision_label' => $proposal->decision?->label(),
            'missing_for_submission' => $proposal->missingForSubmission(),
            'nordeste' => $this->nordeste,
            'speakers_hidden' => $this->speakers === null,
            'speakers' => $this->when($this->speakers !== null, $this->speakers),
            'submitted_at' => $this->isoDate($proposal->submitted_at),
            'updated_at' => $this->isoDate($proposal->updated_at),
        ];
    }
}
