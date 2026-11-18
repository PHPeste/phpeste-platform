<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Cfp\Models\CfpTrack;

class CfpTrackResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $track = $this->resource;

        if (!$track instanceof CfpTrack) {
            return [];
        }

        return [
            'id' => $track->id,
            'name' => $track->name,
            'slots' => $track->slots,
            'position' => $track->position,
            'proposals_count' => $track->submittedProposalsCount(),
        ];
    }
}
