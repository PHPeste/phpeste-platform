<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PHPeste\Notifications\Webhooks\ProcessResendWebhook;
use PHPeste\Notifications\Webhooks\SvixSignature;

/**
 * POST /api/v1/webhooks/resend. Sem assinatura válida (ou com timestamp
 * fora da janela de 5 minutos) responde 401 e não muda nada.
 */
class ResendWebhookController
{
    public function __invoke(Request $request, ProcessResendWebhook $process): JsonResponse
    {
        $body = $request->getContent();
        $signature = new SvixSignature((string) config('services.resend.webhook_secret'));

        if (!$signature->valid($request->header('svix-id'), $request->header('svix-timestamp'), $request->header('svix-signature'), $body)) {
            Log::warning('Webhook do Resend com assinatura inválida.', ['svix_id' => $request->header('svix-id')]);

            return response()->json(['code' => 'invalid_signature', 'message' => 'Assinatura inválida.'], 401);
        }

        $payload = json_decode($body, true);

        if (!is_array($payload)) {
            return response()->json(['code' => 'invalid_payload', 'message' => 'Corpo inválido.'], 400);
        }

        /** @var array<string, mixed> $payload */
        $outcome = $process->handle((string) $request->header('svix-id'), $payload);

        return response()->json(['data' => ['outcome' => $outcome, 'duplicate' => $outcome === 'duplicate']]);
    }
}
