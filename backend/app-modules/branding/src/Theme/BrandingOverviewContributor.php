<?php

declare(strict_types=1);

namespace PHPeste\Branding\Theme;

use PHPeste\Branding\Models\EditionDomain;
use PHPeste\Branding\Models\EditionSetting;
use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Community\Data\OverviewContribution;
use PHPeste\Community\Enums\EditionPermission;

class BrandingOverviewContributor implements EditionOverviewContributor
{
    public function contribute(string $editionId): OverviewContribution
    {
        $pending = [];

        if (EditionSetting::query()->forEdition($editionId)->whereNotNull('theme_published')->doesntExist()) {
            $pending[] = ['key' => 'theme_unpublished', 'title' => 'Identidade da edição ainda não publicada', 'tone' => 'info', 'tag' => 'Site', 'action_label' => 'Publicar identidade', 'action_path' => '/identidade', 'priority' => 3, 'permission' => EditionPermission::ManageEdition];
        }

        if (EditionDomain::query()->forEdition($editionId)->whereNotNull('verified_at')->doesntExist()) {
            $pending[] = ['key' => 'domain_missing', 'title' => 'Site da edição sem domínio verificado', 'tone' => 'info', 'tag' => 'Site', 'action_label' => 'Configurar domínio', 'action_path' => '/configuracoes?aba=dominios', 'priority' => 3, 'permission' => EditionPermission::ManageEdition];
        }

        return new OverviewContribution(pending: $pending);
    }
}
