<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPeste\Community\Contracts\EditionDirectory;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Payments\Contracts\PaymentAccounts;
use PHPeste\Ticketing\Data\Allocation;
use PHPeste\Ticketing\Data\OrderAttendee;
use PHPeste\Ticketing\Data\OrderRequest;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Enums\TicketVisibility;
use PHPeste\Ticketing\Events\OrderPlaced;
use PHPeste\Ticketing\Events\OrderStatusChanged;
use PHPeste\Ticketing\Inventory\CouponPricing;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\OrderItem;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketingSetting;
use PHPeste\Ticketing\Models\TicketType;
use PHPeste\Ticketing\Orders\AnswerValidator;
use PHPeste\Ticketing\Orders\Split;
use PHPeste\Ticketing\Rules\Cpf;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PlaceOrder
{
    public const array SELLING = ['sales_open', 'ongoing'];

    public function __construct(
        private readonly CurrentEdition $currentEdition,
        private readonly EditionDirectory $editions,
        private readonly AllocateTickets $allocate,
        private readonly CouponPricing $pricing,
        private readonly RedeemCoupon $redeem,
        private readonly AnswerValidator $answers,
        private readonly PaymentAccounts $accounts,
    ) {}

    /**
     * @return array{order: Order, created: bool}
     */
    public function handle(string $editionId, OrderRequest $request, string $idempotencyKey): array
    {
        /** @var array{order: Order, created: bool} */
        return $this->currentEdition->run($editionId, function () use ($editionId, $request, $idempotencyKey): array {
            $existing = $this->replay($idempotencyKey, $request);

            if ($existing instanceof Order) {
                return ['order' => $existing, 'created' => false];
            }

            if (!in_array($this->editions->summary($editionId)['status'] ?? null, self::SELLING, true)) {
                throw ValidationException::withMessages(['items' => 'As vendas desta edição não estão abertas.']);
            }

            try {
                $order = DB::transaction(fn (): Order => $this->place($editionId, $request, $idempotencyKey));
            } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
                $existing = $this->replay($idempotencyKey, $request);

                if ($existing instanceof Order) {
                    return ['order' => $existing, 'created' => false];
                }

                throw $uniqueConstraintViolationException;
            }

            return ['order' => $order, 'created' => true];
        });
    }

    private function replay(string $idempotencyKey, OrderRequest $request): ?Order
    {
        $order = Order::query()->with('items')->where('idempotency_key', $idempotencyKey)->first();

        throw_if($order instanceof Order && !hash_equals($order->request_hash, $request->hash()), ConflictHttpException::class, 'Esta Idempotency-Key já foi usada com outros dados de pedido.');

        return $order;
    }

    private function place(string $editionId, OrderRequest $request, string $idempotencyKey): Order
    {
        $settings = TicketingSetting::query()->firstOrCreate(['edition_id' => $editionId]);
        $types = TicketType::query()->with('batches')->whereIn('id', array_column($request->items, 'ticket_type_id'))->get()->keyBy('id');
        $documentHash = $request->buyerDocument === null ? null : hash_hmac('sha256', Cpf::digits($request->buyerDocument), (string) config('app.key'));

        $this->ensureBuyerLimit($settings, $request, $documentHash);

        $lines = [];
        $errors = [];

        foreach ($request->items as $index => $item) {
            $type = $types->get($item['ticket_type_id']);

            if (!$type instanceof TicketType || !$this->purchasable($type, $request->accessCode)) {
                $errors["items.{$index}.ticket_type_id"] = 'Este ingresso não está disponível.';

                continue;
            }

            $batch = $type->currentBatch();

            if (!$batch instanceof TicketBatch) {
                $errors["items.{$index}.ticket_type_id"] = sprintf('"%s" não está à venda agora.', $type->name);

                continue;
            }

            $attendees = [];

            foreach ($item['attendees'] as $position => $attendee) {
                [$clean, $answerErrors] = $this->answers->validate($type, $attendee->answers, "items.{$index}.attendees.{$position}");
                $errors = [...$errors, ...$answerErrors];
                $attendees[] = new OrderAttendee($attendee->name, $attendee->email, $clean);
            }

            $lines[] = ['type' => $type, 'batch_id' => $batch->id, 'attendees' => $attendees];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $allocations = array_map(fn (array $line): Allocation => $this->allocate->handle($editionId, $line['batch_id'], count($line['attendees'])), $lines);

        $quote = $this->pricing->quote($editionId, array_map(fn (Allocation $allocation): array => [
            'ticket_type_id' => $allocation->ticketTypeId,
            'quantity' => $allocation->quantity,
            'unit_price_cents' => $allocation->unitPriceCents,
        ], $allocations), $request->couponCode);

        $fees = [];

        foreach ($quote->lines as $index => $line) {
            $fees[$index] = $lines[$index]['type']->pass_fee_to_buyer ? Split::fee($line->totalCents(), $settings->gateway_fee_basis_points) : 0;
        }

        $total = $quote->totalCents() + array_sum($fees);

        $account = $total > 0 ? $this->accounts->active($editionId) : null;

        if ($total > 0 && $account?->ready !== true) {
            throw ValidationException::withMessages(['items' => 'Vendas pausadas até o teste do gateway.']);
        }

        if ($total > 0 && $request->buyerDocument === null) {
            throw ValidationException::withMessages(['buyer_document' => 'Informe o CPF de quem paga.']);
        }

        TicketingSetting::query()->whereKey($settings->id)->increment('order_sequence');
        $number = (int) TicketingSetting::query()->whereKey($settings->id)->value('order_sequence');
        $free = $total === 0;

        $order = Order::create([
            'edition_id' => $editionId,
            'number' => $number,
            'status' => $free ? OrderStatus::Paid : OrderStatus::Created,
            'user_id' => $request->userId,
            'buyer_name' => $request->buyerName,
            'buyer_email' => $request->buyerEmail,
            'buyer_document' => $request->buyerDocument === null ? null : Cpf::digits($request->buyerDocument),
            'buyer_document_hash' => $documentHash,
            'subtotal_cents' => $quote->subtotalCents(),
            'discount_cents' => $quote->discountCents(),
            'fee_cents' => array_sum($fees),
            'total_cents' => $total,
            'coupon_id' => $quote->couponId,
            'coupon_code' => $quote->couponCode,
            'payment_account_id' => $account?->id,
            'access_token' => Str::random(48),
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $request->hash(),
            'expires_at' => $free ? null : now()->addMinutes($settings->reservation_minutes),
            'paid_at' => $free ? now() : null,
        ]);

        if ($quote->couponId !== null) {
            $redemption = $this->redeem->handle($editionId, $quote->couponId, $quote->discountCents(), (string) $order->number, $request->buyerEmail);
            $order->update(['coupon_redemption_id' => $redemption->id]);
        }

        foreach ($lines as $index => $line) {
            $allocation = $allocations[$index];
            $quoteLine = $quote->lines[$index];
            $count = count($line['attendees']);
            $discounts = Split::evenly($quoteLine->discountCents, $count);
            $lineFees = Split::evenly($fees[$index], $count);

            foreach ($line['attendees'] as $position => $attendee) {
                OrderItem::create([
                    'edition_id' => $editionId,
                    'order_id' => $order->id,
                    'ticket_type_id' => $allocation->ticketTypeId,
                    'ticket_batch_id' => $allocation->ticketBatchId,
                    'ticket_type_name' => $line['type']->name,
                    'batch_name' => $allocation->batchName,
                    'unit_price_cents' => $allocation->unitPriceCents,
                    'discount_cents' => $discounts[$position],
                    'fee_cents' => $lineFees[$position],
                    'total_cents' => $allocation->unitPriceCents - $discounts[$position] + $lineFees[$position],
                    'attendee_name' => $attendee->name,
                    'attendee_email' => $attendee->email,
                    'answers' => $attendee->answers === [] ? null : $attendee->answers,
                ]);
            }
        }

        event(new OrderPlaced($editionId, $order->id, !$free));

        if ($free) {
            event(new OrderStatusChanged($editionId, $order->id, OrderStatus::Created, OrderStatus::Paid, $request->userId, 'free'));
        }

        return $order->load('items');
    }

    private function purchasable(TicketType $type, ?string $accessCode): bool
    {
        return match ($type->visibility) {
            TicketVisibility::Public => true,
            TicketVisibility::Code => $accessCode !== null && hash_equals((string) $type->access_code, mb_strtoupper(mb_trim($accessCode))),
            TicketVisibility::Hidden => false,
        };
    }

    private function ensureBuyerLimit(TicketingSetting $settings, OrderRequest $request, ?string $documentHash): void
    {
        if ($settings->max_tickets_per_buyer === null) {
            return;
        }

        $already = OrderItem::query()
            ->whereHas('order', fn (Builder $query) => $query->whereIn('status', OrderStatus::holding())
                ->where(fn (Builder $buyer) => $buyer->where('buyer_email', $request->buyerEmail)
                    ->when($documentHash !== null, fn ($either) => $either->orWhere('buyer_document_hash', $documentHash))))
            ->count();

        if ($already + $request->quantity() > $settings->max_tickets_per_buyer) {
            $left = max(0, $settings->max_tickets_per_buyer - $already);

            throw ValidationException::withMessages(['items' => $left === 0
                ? sprintf('Cada pessoa compra no máximo %d ingressos nesta edição, e esse limite já foi atingido com este e-mail ou CPF.', $settings->max_tickets_per_buyer)
                : sprintf('Cada pessoa compra no máximo %d ingressos nesta edição; com este e-mail ou CPF ainda dá para comprar %d.', $settings->max_tickets_per_buyer, $left)]);
        }
    }
}
