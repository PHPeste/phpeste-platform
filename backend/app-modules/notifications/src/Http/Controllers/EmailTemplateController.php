<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Notifications\Actions\QueueEmail;
use PHPeste\Notifications\Actions\RestoreDefaultEmailTemplate;
use PHPeste\Notifications\Actions\SaveEmailTemplate;
use PHPeste\Notifications\Enums\DeliveryStatus;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Notifications\Http\Requests\EmailTemplateRequest;
use PHPeste\Notifications\Http\Requests\PreviewEmailTemplateRequest;
use PHPeste\Notifications\Http\Requests\SaveEmailTemplateRequest;
use PHPeste\Notifications\Http\Resources\EmailDeliveryResource;
use PHPeste\Notifications\Http\Resources\EmailTemplateResource;
use PHPeste\Notifications\Mail\EmailComposer;
use PHPeste\Notifications\Models\EditionEmailTemplate;
use PHPeste\Notifications\Models\EmailDelivery;

class EmailTemplateController
{
    public function __construct(
        private readonly EmailComposer $composer,
        private readonly CurrentEdition $currentEdition,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('manage-edition-emails');

        $customized = EditionEmailTemplate::query()->pluck('template')->map(fn (EmailTemplate $template): string => $template->value)->all();

        return response()->json([
            'data' => array_map(
                fn (EmailTemplate $template): array => $this->resource($template, in_array($template->value, $customized, true))->resolve(),
                EmailTemplate::cases(),
            ),
        ]);
    }

    public function show(EmailTemplateRequest $request): EmailTemplateResource
    {
        Gate::authorize('manage-edition-emails');

        $template = $request->template();

        return $this->resource($template, EditionEmailTemplate::query()->where('template', $template)->exists());
    }

    public function update(SaveEmailTemplateRequest $request, SaveEmailTemplate $saveTemplate): EmailTemplateResource
    {
        Gate::authorize('manage-edition-emails');

        $template = $request->template();
        $saveTemplate->handle($template, $request->string('subject')->toString(), $request->string('body')->toString());

        return $this->resource($template, true);
    }

    public function destroy(EmailTemplateRequest $request, RestoreDefaultEmailTemplate $restoreDefault): EmailTemplateResource
    {
        Gate::authorize('manage-edition-emails');

        $template = $request->template();
        $restoreDefault->handle($template);

        return $this->resource($template, false);
    }

    public function preview(PreviewEmailTemplateRequest $request): JsonResponse
    {
        Gate::authorize('manage-edition-emails');

        $template = $request->template();
        $editionId = $this->editionId();

        $email = $this->composer->compose(
            $editionId,
            $template,
            'preview@phpeste.dev',
            $template->sampleVariables(),
            '#',
            $request->contentOver($this->composer->content($editionId, $template)),
        );

        return response()->json(['data' => ['subject' => $email->subject, 'html' => $email->html, 'text' => $email->text]]);
    }

    public function sendTest(PreviewEmailTemplateRequest $request, QueueEmail $queueEmail): JsonResponse
    {
        Gate::authorize('manage-edition-emails');

        $template = $request->template();
        $editionId = $this->editionId();
        $user = $request->user();

        $email = $user?->getAttribute('email');
        $name = $user?->getAttribute('name');

        abort_unless(is_string($email) && is_string($name), 403);

        $delivery = $queueEmail->queue(
            $editionId,
            $template,
            $email,
            $name,
            [...$template->sampleVariables(), 'nome' => $name],
            (string) config('app.url'),
            $request->contentOver($this->composer->content($editionId, $template)),
            isTest: true,
        );

        return new EmailDeliveryResource($delivery->refresh())->response()->setStatusCode(202);
    }

    private function resource(EmailTemplate $template, bool $customized): EmailTemplateResource
    {
        $lastSentAt = EmailDelivery::query()
            ->where('template', $template)
            ->where('status', DeliveryStatus::Sent)
            ->where('is_test', false)
            ->max('sent_at');

        return new EmailTemplateResource(
            $template,
            $this->composer->content($this->editionId(), $template),
            $customized,
            is_string($lastSentAt) ? Date::parse($lastSentAt) : null,
        );
    }

    private function editionId(): string
    {
        return (string) $this->currentEdition->id();
    }
}
