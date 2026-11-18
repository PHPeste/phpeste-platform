<?php

declare(strict_types=1);

namespace PHPeste\Payments\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Payments\Actions\CreatePaymentAccount;
use PHPeste\Payments\Actions\UpdatePaymentAccount;
use PHPeste\Payments\Actions\VerifyPaymentAccount;
use PHPeste\Payments\Gateway\GatewayRegistry;
use PHPeste\Payments\Http\Requests\CreatePaymentAccountRequest;
use PHPeste\Payments\Http\Requests\UpdatePaymentAccountRequest;
use PHPeste\Payments\Http\Resources\PaymentAccountResource;
use PHPeste\Payments\Models\PaymentAccount;

class PaymentAccountController
{
    public function __construct(private readonly CurrentEdition $currentEdition) {}

    public function index(Request $request, GatewayRegistry $gateways): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageGateway->value);

        $accounts = PaymentAccount::query()->latest()->orderByDesc('id')->get();
        $active = $accounts->first(fn (PaymentAccount $account): bool => $account->isActive());

        return response()->json(['data' => [
            'active' => $active === null ? null : new PaymentAccountResource($active)->resolve($request),
            'previous' => PaymentAccountResource::collection($accounts->reject(fn (PaymentAccount $account): bool => $account->isActive())->values())->resolve($request),
            'drivers' => $gateways->available(),
        ]]);
    }

    public function store(CreatePaymentAccountRequest $request, CreatePaymentAccount $create): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageGateway->value);

        $account = $create->handle((string) $this->currentEdition->id(), $request->account(), $request->boolean('confirm'), $this->actor($request));

        return new PaymentAccountResource($account)->response()->setStatusCode(201);
    }

    public function update(UpdatePaymentAccountRequest $request, UpdatePaymentAccount $update): PaymentAccountResource
    {
        Gate::authorize(EditionPermission::ManageGateway->value);

        /** @var array<string, mixed> $attributes */
        $attributes = $request->validated();

        return new PaymentAccountResource($update->handle($this->account($request), $attributes));
    }

    public function verify(Request $request, VerifyPaymentAccount $verify): PaymentAccountResource
    {
        Gate::authorize(EditionPermission::ManageGateway->value);

        return new PaymentAccountResource($verify->handle($this->account($request), $this->actor($request)));
    }

    private function account(Request $request): PaymentAccount
    {
        $id = $request->route('account');
        abort_unless(is_string($id), 404);

        return PaymentAccount::query()->whereKey($id)->firstOrFail();
    }

    private function actor(Request $request): ?string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_string($id) ? $id : null;
    }
}
