<?php

declare(strict_types=1);

namespace PHPeste\Payments\Overview;

use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Community\Data\OverviewContribution;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Payments\Enums\WebhookEventStatus;
use PHPeste\Payments\Models\WebhookEventRecord;

class PaymentsOverviewContributor implements EditionOverviewContributor
{
    public function contribute(string $editionId): OverviewContribution
    {
        $failed = WebhookEventRecord::query()->forEdition($editionId)->where('status', WebhookEventStatus::Failed->value)->count();

        if ($failed === 0) {
            return new OverviewContribution;
        }

        return new OverviewContribution(pending: [[
            'key' => 'webhooks_failed',
            'title' => sprintf('%d %s do gateway com falha', $failed, $failed === 1 ? 'aviso' : 'avisos'),
            'tone' => 'warn',
            'tag' => 'Pagamentos',
            'action_label' => 'Ver avisos',
            'action_path' => '/configuracoes?aba=pagamentos&avisos=failed',
            'priority' => 1,
            'permission' => EditionPermission::ManageGateway,
        ]]);
    }
}
