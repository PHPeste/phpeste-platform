<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Webhooks;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Notifications\Enums\DeliveryStatus;
use PHPeste\Notifications\Enums\SuppressionReason;
use PHPeste\Notifications\Events\AddressSuppressionChanged;
use PHPeste\Notifications\Models\EmailDelivery;
use PHPeste\Notifications\Models\EmailSuppression;
use PHPeste\Notifications\Models\EmailWebhookEvent;

/**
 * Aplica um evento do Resend ao envio. A edição vem do envio achado pelo
 * `provider_message_id` (o id do Resend, que o transport grava como
 * Message-ID), nunca de algo que o webhook mande (ADR 0007).
 *
 * - email.delivered: entregue;
 * - email.bounced permanente: voltou e o endereço fica bloqueado na edição;
 * - email.bounced temporário / email.delivery_delayed: só registra o motivo;
 * - email.complained: marcado como spam e o endereço fica bloqueado.
 */
final readonly class ProcessResendWebhook
{
    public function __construct(private CurrentEdition $currentEdition) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return string `processed`, `ignored` ou `duplicate`
     */
    public function handle(string $svixId, array $payload): string
    {
        $type = is_string($payload['type'] ?? null) ? $payload['type'] : 'unknown';
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $messageId = is_string($data['email_id'] ?? null) ? $data['email_id'] : null;
        $delivery = $messageId === null ? null : EmailDelivery::query()->withoutGlobalScopes()->where('provider_message_id', $messageId)->first();

        try {
            return DB::transaction(function () use ($svixId, $type, $data, $messageId, $delivery): string {
                $outcome = $delivery instanceof EmailDelivery
                    ? (string) $this->currentEdition->run($delivery->edition_id, fn (): string => $this->apply($delivery, $type, $data))
                    : 'ignored';

                EmailWebhookEvent::create([
                    'svix_id' => $svixId,
                    'type' => mb_substr($type, 0, 40),
                    'provider_message_id' => $messageId === null ? null : mb_substr($messageId, 0, 100),
                    'edition_id' => $delivery?->edition_id,
                    'outcome' => $outcome,
                ]);

                return $outcome;
            });
        } catch (UniqueConstraintViolationException) {
            return 'duplicate';
        }
    }

    /**
     * @param  array<mixed>  $data  o `data` do evento, como veio do Resend
     */
    private function apply(EmailDelivery $delivery, string $type, array $data): string
    {
        $bounce = is_array($data['bounce'] ?? null) ? $data['bounce'] : [];
        $detail = mb_substr(mb_trim(implode(' · ', array_filter([
            is_string($bounce['type'] ?? null) ? $bounce['type'] : null,
            is_string($bounce['subType'] ?? null) ? $bounce['subType'] : null,
            is_string($bounce['message'] ?? null) ? $bounce['message'] : null,
        ]))), 0, 500);

        switch ($type) {
            case 'email.delivered':
                if (in_array($delivery->status, [DeliveryStatus::Sent, DeliveryStatus::Queued], true)) {
                    $delivery->update(['status' => DeliveryStatus::Delivered, 'delivered_at' => now()]);
                }

                return 'processed';

            case 'email.bounced':
                if (mb_strtolower((string) ($bounce['type'] ?? '')) !== 'permanent') {
                    $delivery->update(['provider_detail' => 'Bounce temporário'.($detail === '' ? '' : ': '.$detail)]);

                    return 'processed';
                }

                $delivery->update(['status' => DeliveryStatus::Bounced, 'bounced_at' => now(), 'provider_detail' => $detail === '' ? 'Bounce permanente' : $detail]);
                $this->suppress($delivery, SuppressionReason::Bounced, $detail);

                return 'processed';

            case 'email.delivery_delayed':
                $delivery->update(['provider_detail' => 'Entrega atrasada: o servidor de destino ainda não aceitou']);

                return 'processed';

            case 'email.complained':
                $delivery->update(['status' => DeliveryStatus::Complained, 'complained_at' => now()]);
                $this->suppress($delivery, SuppressionReason::Complained, null);

                return 'processed';

            default:
                return 'ignored';
        }
    }

    private function suppress(EmailDelivery $delivery, SuppressionReason $reason, ?string $detail): void
    {
        $suppression = EmailSuppression::query()->forEdition($delivery->edition_id)->firstOrCreate(
            ['edition_id' => $delivery->edition_id, 'email' => $delivery->to_email],
            ['reason' => $reason, 'detail' => $detail === '' ? null : $detail, 'email_delivery_id' => $delivery->id],
        );

        if ($suppression->wasRecentlyCreated) {
            Log::warning('Endereço bloqueado por bounce ou reclamação.', ['edition_id' => $delivery->edition_id, 'reason' => $reason->value, 'delivery_id' => $delivery->id]);
            event(new AddressSuppressionChanged($delivery->edition_id, $suppression->id, $delivery->to_email, $reason->value, false, null));
        }
    }
}
