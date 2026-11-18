<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PHPeste\Notifications\Enums\EmailTemplate;

class EmailTemplateResource extends ApiResource
{
    /**
     * @param  array{subject: string, body: string}  $content
     */
    public function __construct(
        private readonly EmailTemplate $template,
        private readonly array $content,
        private readonly bool $customized,
        private readonly ?Carbon $lastSentAt,
    ) {
        parent::__construct($template);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->template->value,
            'label' => $this->template->label(),
            'trigger' => $this->template->trigger(),
            'transactional' => $this->template->isTransactional(),
            'variables' => $this->template->variables(),
            'action_label' => $this->template->actionLabel(),
            'subject' => $this->content['subject'],
            'body' => $this->content['body'],
            'customized' => $this->customized,
            'last_sent_at' => $this->isoDate($this->lastSentAt),
        ];
    }
}
