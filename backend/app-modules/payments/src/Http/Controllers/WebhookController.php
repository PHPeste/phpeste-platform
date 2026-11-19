<?php

declare(strict_types=1);

namespace PHPeste\Payments\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPeste\Payments\Actions\ReceiveWebhook;
use PHPeste\Payments\Gateway\InvalidWebhookSignature;
use PHPeste\Payments\Models\PaymentAccount;

class WebhookController
{
    public function __invoke(Request $request, string $token, ReceiveWebhook $receive): JsonResponse
    {
        $account = PaymentAccount::query()->withoutGlobalScopes()->where('webhook_token', $token)->first();
        abort_unless($account instanceof PaymentAccount, 404);

        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[mb_strtolower((string) $name)] = (string) ($values[0] ?? '');
        }

        try {
            $event = $receive->handle($account, $headers, $request->getContent());
        } catch (InvalidWebhookSignature) {
            return response()->json(['code' => 'invalid_signature', 'message' => 'Assinatura do webhook inválida.', 'errors' => null, 'request_id' => $request->attributes->get('request_id')], 401);
        }

        return response()->json(['data' => ['received' => true, 'event_id' => $event->event_id, 'duplicate' => !$event->wasRecentlyCreated]]);
    }
}
