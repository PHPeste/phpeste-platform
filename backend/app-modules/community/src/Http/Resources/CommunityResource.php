<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Community\Models\Community;

class CommunityResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $community = $this->resource;

        if (!$community instanceof Community) {
            return [];
        }

        return [
            'id' => $community->id,
            'name' => $community->name,
            'slug' => $community->slug,
            'created_at' => $this->isoDate($community->created_at),
        ];
    }
}
