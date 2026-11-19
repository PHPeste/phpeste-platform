<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Orders;

use PHPeste\Audit\Contracts\AuditTrail;
use PHPeste\Audit\Data\AuditEntry;
use PHPeste\Identity\Contracts\UserAccounts;
use PHPeste\Payments\Enums\PaymentMethod;
use PHPeste\Payments\Enums\RefundReason;
use PHPeste\Ticketing\Actions\MarkOrderPaid;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Models\Order;

/**
 * Linha do tempo do pedido para o painel: a criação e tudo o que a
 * auditoria registrou sobre ele, em frases prontas para mostrar.
 */
class OrderHistory
{
    public function __construct(
        private readonly AuditTrail $trail,
        private readonly UserAccounts $accounts,
    ) {}

    /**
     * @return list<array{id: string, kind: string, title: string, detail: string|null, actor: array{id: string, name: string|null}|null, at: string}>
     */
    public function for(Order $order): array
    {
        $entries = $this->trail->forSubject($order->edition_id, 'order', $order->id);
        $actorIds = array_values(array_unique(array_filter(array_map(fn (AuditEntry $entry): ?string => $entry->actorId, $entries))));
        $profiles = $actorIds === [] ? [] : $this->accounts->profiles($actorIds);

        $created = [
            'id' => 'created',
            'kind' => 'created',
            'title' => $order->external() ? sprintf('Pedido importado de %s', $order->external_platform) : 'Pedido criado',
            'detail' => null,
            'actor' => null,
            'at' => $order->created_at->clone()->utc()->toIso8601ZuluString(),
        ];

        return [$created, ...array_map(function (AuditEntry $entry) use ($profiles): array {
            [$kind, $title, $detail] = $this->describe($entry);

            return [
                'id' => $entry->id,
                'kind' => $kind,
                'title' => $title,
                'detail' => $detail,
                'actor' => $entry->actorId === null ? null : ['id' => $entry->actorId, 'name' => $profiles[$entry->actorId]['name'] ?? null],
                'at' => $entry->createdAt->utc()->toIso8601ZuluString(),
            ];
        }, $entries)];
    }

    /**
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function describe(AuditEntry $entry): array
    {
        $after = $entry->after ?? [];

        return match ($entry->action) {
            'ticketing.order_status_changed' => $this->statusChange($after),
            'ticketing.order_refunded' => [
                'refunded',
                sprintf('Reembolso de %s', $this->reais(is_int($after['amount_cents'] ?? null) ? $after['amount_cents'] : 0)),
                $this->join([
                    RefundReason::tryFrom(is_string($after['reason'] ?? null) ? $after['reason'] : '')?->label(),
                    is_string($after['note'] ?? null) ? $after['note'] : null,
                    is_int($after['cancelled_tickets'] ?? null) && $after['cancelled_tickets'] > 0 ? sprintf('%d ingresso(s) cancelado(s)', $after['cancelled_tickets']) : null,
                ]),
            ],
            'ticketing.payment_reminder_sent' => ['reminder', 'Cobrança reenviada por e-mail', null],
            default => ['other', $entry->action, null],
        };
    }

    /**
     * @param  array<string, mixed>  $after
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function statusChange(array $after): array
    {
        $to = OrderStatus::tryFrom(is_string($after['status'] ?? null) ? $after['status'] : '');
        $reason = is_string($after['reason'] ?? null) ? $after['reason'] : null;

        if ($to === OrderStatus::Paid && $reason !== null && str_starts_with($reason, MarkOrderPaid::REASON_PREFIX)) {
            return ['marked_paid', 'Marcado como pago manualmente', mb_substr($reason, mb_strlen(MarkOrderPaid::REASON_PREFIX))];
        }

        return match ($to) {
            OrderStatus::AwaitingPayment => ['payment_started', 'Pagamento iniciado', PaymentMethod::tryFrom((string) $reason)?->label()],
            OrderStatus::Paid => ['paid', 'Pagamento confirmado', null],
            OrderStatus::Expired => ['expired', 'Reserva expirou sem pagamento', null],
            OrderStatus::Cancelled => ['cancelled', 'Pedido cancelado', $reason === 'organizer' ? 'Pela organização' : ($reason === 'buyer' ? 'Por quem comprou' : null)],
            OrderStatus::Refunded => ['refunded_full', 'Pedido reembolsado por inteiro', null],
            OrderStatus::Created, null => ['other', 'Situação alterada', null],
        };
    }

    private function reais(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }

    /**
     * @param  list<string|null>  $parts
     */
    private function join(array $parts): ?string
    {
        $parts = array_values(array_filter($parts, fn (?string $part): bool => $part !== null && $part !== ''));

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
