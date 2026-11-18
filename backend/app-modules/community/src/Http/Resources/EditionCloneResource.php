<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Community\Models\EditionClone;

class EditionCloneResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $clone = $this->resource;

        if (!$clone instanceof EditionClone) {
            return [];
        }

        return [
            'id' => $clone->id,
            'source_edition_id' => $clone->source_edition_id,
            'target_edition_id' => $clone->target_edition_id,
            'name' => $clone->name,
            'slug' => $clone->slug,
            'parts' => $clone->parts,
            'status' => $clone->status,
            'error' => $clone->error,
            'finished_at' => $this->isoDate($clone->finished_at),
            'created_at' => $this->isoDate($clone->created_at),
        ];
    }
}
