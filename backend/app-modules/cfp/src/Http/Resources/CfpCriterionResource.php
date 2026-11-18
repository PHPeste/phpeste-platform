<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Cfp\Models\CfpCriterion;

class CfpCriterionResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $criterion = $this->resource;

        if (!$criterion instanceof CfpCriterion) {
            return [];
        }

        return [
            'id' => $criterion->id,
            'name' => $criterion->name,
            'description' => $criterion->description,
            'weight' => $criterion->weight,
            'position' => $criterion->position,
        ];
    }
}
