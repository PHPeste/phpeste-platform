<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Actions;

use PHPeste\Notifications\Contracts\EditionMailer;
use PHPeste\Notifications\Enums\DeliveryStatus;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Notifications\Jobs\SendEmailDelivery;
use PHPeste\Notifications\Models\EmailDelivery;
use PHPeste\Notifications\Models\EmailUnsubscribe;

class QueueEmail implements EditionMailer
{
    public function send(string $editionId, EmailTemplate $template, string $email, string $name, array $variables = [], ?string $actionUrl = null): string
    {
        return $this->queue($editionId, $template, $email, $name, $variables, $actionUrl)->id;
    }

    /**
     * @param  array<string, string>  $variables
     * @param  array{subject: string, body: string}|null  $content
     */
    public function queue(
        string $editionId,
        EmailTemplate $template,
        string $email,
        string $name,
        array $variables = [],
        ?string $actionUrl = null,
        ?array $content = null,
        bool $isTest = false,
    ): EmailDelivery {
        $email = mb_strtolower(mb_trim($email));
        $suppressed = !$template->isTransactional() && !$isTest && $this->isUnsubscribed($editionId, $email);

        $delivery = EmailDelivery::create([
            'edition_id' => $editionId,
            'template' => $template,
            'to_email' => $email,
            'to_name' => $name,
            'variables' => array_intersect_key($variables, array_flip($template->variables())),
            'content' => $content,
            'action_url' => $actionUrl,
            'status' => $suppressed ? DeliveryStatus::Suppressed : DeliveryStatus::Queued,
            'is_test' => $isTest,
        ]);

        if (!$suppressed) {
            dispatch(new SendEmailDelivery($editionId, $delivery->id));
        }

        return $delivery;
    }

    private function isUnsubscribed(string $editionId, string $email): bool
    {
        return EmailUnsubscribe::query()->forEdition($editionId)->where('email', $email)->exists();
    }
}
