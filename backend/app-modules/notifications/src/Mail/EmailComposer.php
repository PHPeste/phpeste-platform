<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Mail;

use Illuminate\Support\Facades\URL;
use PHPeste\Branding\Contracts\EditionThemes;
use PHPeste\Community\Contracts\EditionDirectory;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Notifications\Models\EditionEmailTemplate;
use RuntimeException;

class EmailComposer
{
    public function __construct(
        private readonly EditionDirectory $editions,
        private readonly EditionThemes $themes,
    ) {}

    /**
     * @param  array<string, string>  $variables
     * @param  array{subject: string, body: string}|null  $content
     */
    public function compose(
        string $editionId,
        EmailTemplate $template,
        string $recipientEmail,
        array $variables,
        ?string $actionUrl = null,
        ?array $content = null,
    ): RenderedEmail {
        $edition = $this->editions->summary($editionId)
            ?? throw new RuntimeException("Edição {$editionId} não encontrada para compor e-mail.");

        $content ??= $this->content($editionId, $template);
        $dates = EditionDates::format($edition['starts_on'], $edition['ends_on']);
        $variables = [...$variables, 'edicao' => $edition['name'], 'data' => $dates];

        $unsubscribeUrl = $template->isTransactional()
            ? null
            : URL::signedRoute('notifications.unsubscribe', ['edition' => $editionId, 'email' => $recipientEmail]);

        $subject = TemplateRenderer::singleLine($content['subject'], $variables);
        $theme = $this->themes->published($editionId);
        $logo = $theme['logos']['primary'] ?? null;

        $html = view('notifications::email', [
            'subject' => $subject,
            'paragraphs' => TemplateRenderer::htmlParagraphs($content['body'], $variables),
            'colors' => $theme['colors'],
            'font' => $theme['fonts']['body'],
            'radius' => $theme['radius'],
            'logoUrl' => is_string($logo) && str_ends_with(mb_strtolower($logo), '.png') ? $logo : null,
            'editionName' => $edition['name'],
            'footer' => implode(' · ', array_filter([$edition['name'], $edition['starts_on'] === null ? null : $dates, $edition['city']])),
            'actionLabel' => $template->actionLabel(),
            'actionUrl' => $actionUrl,
            'unsubscribeUrl' => $unsubscribeUrl,
        ])->render();

        $text = TemplateRenderer::plain($content['body'], $variables)
            .($actionUrl === null ? '' : "\n\n{$template->actionLabel()}: {$actionUrl}")
            .($unsubscribeUrl === null ? '' : "\n\nNão quer mais receber estes avisos? {$unsubscribeUrl}");

        return new RenderedEmail($edition['name'], $subject, $html, $text, $unsubscribeUrl);
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function content(string $editionId, EmailTemplate $template): array
    {
        $custom = EditionEmailTemplate::query()
            ->forEdition($editionId)
            ->where('template', $template)
            ->first();

        return [
            'subject' => $custom->subject ?? $template->defaultSubject(),
            'body' => $custom->body ?? $template->defaultBody(),
        ];
    }
}
