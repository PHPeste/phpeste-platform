<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Controllers;

use PHPeste\Community\Http\Resources\PublicEditionResource;
use PHPeste\Community\Models\Community;
use PHPeste\Community\Models\Edition;

class PublicEditionController
{
    public function __invoke(Community $community, Edition $edition): PublicEditionResource
    {
        abort_unless($edition->status->isPublished(), 404);

        return new PublicEditionResource($edition->setRelation('community', $community));
    }
}
