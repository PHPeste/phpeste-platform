<?php

declare(strict_types=1);

namespace PHPeste\Payments\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Payments\Actions\ReprocessWebhookEvent;
use PHPeste\Payments\Enums\WebhookEventStatus;
use PHPeste\Payments\Http\Resources\WebhookEventResource;
use PHPeste\Payments\Models\WebhookEventRecord;

class WebhookEventController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(EditionPermission::ManageGateway->value);

        $status = WebhookEventStatus::tryFrom((string) $request->query('status'));
        $charge = mb_trim((string) $request->query('charge'));

        $events = WebhookEventRecord::query()
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->when($charge !== '', fn ($query) => $query->where('gateway_charge_id', $charge))
            ->latest()
            ->orderByDesc('id')
            ->paginate(25);

        return WebhookEventResource::collection($events);
    }

    public function reprocess(Request $request, ReprocessWebhookEvent $reprocess): WebhookEventResource
    {
        Gate::authorize(EditionPermission::ManageGateway->value);

        $id = $request->route('event');
        abort_unless(is_string($id), 404);

        $actor = $request->user()?->getAuthIdentifier();

        return new WebhookEventResource($reprocess->handle(WebhookEventRecord::query()->whereKey($id)->firstOrFail(), is_string($actor) ? $actor : null));
    }
}
