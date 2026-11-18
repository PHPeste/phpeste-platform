<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use PHPeste\Community\Models\Community;

class CreateCommunity
{
    public function handle(string $name, string $slug, string $createdBy): Community
    {
        return Community::create([
            'name' => $name,
            'slug' => $slug,
            'created_by' => $createdBy,
        ]);
    }
}
