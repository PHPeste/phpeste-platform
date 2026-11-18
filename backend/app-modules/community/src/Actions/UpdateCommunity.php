<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use PHPeste\Community\Models\Community;

class UpdateCommunity
{
    /**
     * @param  array{name?: string, slug?: string}  $attributes
     */
    public function handle(Community $community, array $attributes): Community
    {
        $community->update($attributes);

        return $community;
    }
}
