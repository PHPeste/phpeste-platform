<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use PHPeste\Community\Contracts\EditionDirectory;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Data\QuoteLine;
use PHPeste\Ticketing\Http\Requests\CheckCouponRequest;
use PHPeste\Ticketing\Inventory\CouponPricing;
use PHPeste\Ticketing\Models\TicketType;

class PublicCouponController
{
    public function __invoke(CheckCouponRequest $request, string $edition, EditionDirectory $editions, CurrentEdition $currentEdition, CouponPricing $pricing): JsonResponse
    {
        abort_unless($editions->isPublished($edition), 404);

        /** @var JsonResponse */
        return $currentEdition->run($edition, function () use ($request, $edition, $pricing): JsonResponse {
            $items = [];

            foreach ($request->items() as $item) {
                $type = TicketType::query()->with('batches')->find($item['ticket_type_id']);
                $batch = $type?->currentBatch();

                if ($batch === null) {
                    throw ValidationException::withMessages(['items' => 'Algum dos ingressos escolhidos não está à venda.']);
                }

                $items[] = ['ticket_type_id' => $item['ticket_type_id'], 'quantity' => $item['quantity'], 'unit_price_cents' => $batch->price_cents];
            }

            $quote = $pricing->quote($edition, $items, (string) $request->validated('code'));

            return response()->json(['data' => [
                'code' => $quote->couponCode,
                'subtotal' => ['amount' => $quote->subtotalCents(), 'currency' => 'BRL'],
                'discount' => ['amount' => $quote->discountCents(), 'currency' => 'BRL'],
                'total' => ['amount' => $quote->totalCents(), 'currency' => 'BRL'],
                'requires_payment' => $quote->requiresPayment(),
                'lines' => array_map(fn (QuoteLine $line): array => [
                    'ticket_type_id' => $line->ticketTypeId,
                    'quantity' => $line->quantity,
                    'discount' => ['amount' => $line->discountCents, 'currency' => 'BRL'],
                    'total' => ['amount' => $line->totalCents(), 'currency' => 'BRL'],
                ], $quote->lines),
            ]]);
        });
    }
}
