<?php

declare(strict_types=1);

namespace PHPeste\Branding\Theme;

use PHPeste\Branding\Contracts\EditionThemes;

class CachedEditionThemes implements EditionThemes
{
    public function published(string $editionId): array
    {
        return PublicTheme::for($editionId)['theme'];
    }
}
