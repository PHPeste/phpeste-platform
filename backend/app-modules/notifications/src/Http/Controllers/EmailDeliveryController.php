<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use PHPeste\Notifications\Actions\ResendEmail;
use PHPeste\Notifications\Http\Requests\ListDeliveriesRequest;
use PHPeste\Notifications\Http\Resources\EmailDeliveryResource;
use PHPeste\Notifications\Models\EmailDelivery;

class EmailDeliveryController
{
    public function index(ListDeliveriesRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('manage-edition-emails');

        $deliveries = EmailDelivery::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('template'), fn ($query) => $query->where('template', $request->string('template')->toString()))
            ->latest()
            ->paginate(25);

        return EmailDeliveryResource::collection($deliveries);
    }

    public function resend(Request $request, ResendEmail $resendEmail): EmailDeliveryResource
    {
        Gate::authorize('manage-edition-emails');

        $deliveryId = $request->route('delivery');

        abort_unless(is_string($deliveryId), 404);

        $delivery = EmailDelivery::query()->whereKey($deliveryId)->firstOrFail();

        return new EmailDeliveryResource($resendEmail->handle($delivery));
    }
}
