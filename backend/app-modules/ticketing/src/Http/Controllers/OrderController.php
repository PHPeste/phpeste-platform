<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Controllers;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Contracts\EditionDirectory;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Payments\Contracts\PaymentProcessor;
use PHPeste\Payments\Data\PaymentAttempt;
use PHPeste\Ticketing\Actions\MarkOrderPaid;
use PHPeste\Ticketing\Actions\SendPaymentReminder;
use PHPeste\Ticketing\Actions\TransitionOrder;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Http\Requests\MarkOrderPaidRequest;
use PHPeste\Ticketing\Http\Resources\OrderResource;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Orders\OrderExport;
use PHPeste\Ticketing\Orders\OrderHistory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController
{
    /**
     * Pedidos da edição, do mais novo para o mais antigo. `status` filtra pela
     * situação; `q` procura no nome, no e-mail ou no número (com ou sem #).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(EditionPermission::ViewOrders->value);

        return OrderResource::collection($this->filtered($request)->with('items')->paginate(25));
    }

    /**
     * Os mesmos filtros da lista, em CSV separado por ponto e vírgula.
     */
    public function export(Request $request, OrderExport $export, EditionDirectory $editions, CurrentEdition $currentEdition): StreamedResponse
    {
        Gate::authorize(EditionPermission::ViewOrders->value);

        $editionId = (string) $currentEdition->id();
        $orders = $this->filtered($request);
        $timezone = $editions->summary($editionId)['timezone'] ?? 'America/Fortaleza';

        // O corpo é escrito depois que a requisição sai do contexto da edição,
        // por isso o contexto é ligado de novo (pedidos e itens usam o escopo).
        return response()->streamDownload(fn () => $currentEdition->run($editionId, function () use ($export, $orders, $timezone): void {
            $out = fopen('php://output', 'w');

            if ($out !== false) {
                $export->write($orders, $timezone, $out);
                fclose($out);
            }
        }), sprintf('pedidos-%s.csv', now()->format('Y-m-d')), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * O pedido com a cobrança em andamento no gateway, quando houver.
     */
    public function show(Request $request, PaymentProcessor $payments): JsonResponse
    {
        Gate::authorize(EditionPermission::ViewOrders->value);

        $order = $this->order($request);
        $attempt = $order->total_cents > 0 ? $payments->current($order->edition_id, $order->id) : null;

        return new OrderResource($order)->additional(['meta' => [
            'payment' => $attempt instanceof PaymentAttempt ? [
                'method' => $attempt->method->value,
                'method_label' => $attempt->method->label(),
                'status' => $attempt->status->value,
                'status_label' => $attempt->status->label(),
                'installments' => $attempt->installments,
                'expires_at' => $attempt->expiresAt?->clone()->utc()->toIso8601ZuluString(),
                'paid_at' => $attempt->paidAt?->clone()->utc()->toIso8601ZuluString(),
                'failure_reason' => $attempt->failureReason,
            ] : null,
        ]])->response();
    }

    /**
     * Linha do tempo: criação, pagamento, reembolsos, cobranças reenviadas e
     * quem fez cada coisa.
     */
    public function history(Request $request, OrderHistory $history): JsonResponse
    {
        Gate::authorize(EditionPermission::ViewOrders->value);

        return response()->json(['data' => $history->for($this->order($request))]);
    }

    public function cancel(Request $request, TransitionOrder $transition): OrderResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return new OrderResource($transition->handle($this->order($request), OrderStatus::Cancelled, $this->actor($request), 'organizer')->load('items'));
    }

    /**
     * Confirma um pagamento recebido fora do gateway. A justificativa é
     * obrigatória e fica na auditoria; os ingressos são emitidos na hora.
     */
    public function markPaid(MarkOrderPaidRequest $request, MarkOrderPaid $markPaid): OrderResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return new OrderResource($markPaid->handle($this->order($request), $request->justification(), $this->actor($request))->load('items'));
    }

    /**
     * Manda de novo para quem comprou o link de pagamento do pedido.
     */
    public function remind(Request $request, SendPaymentReminder $reminder): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return response()->json(['data' => ['delivery_id' => $reminder->handle($this->order($request), $this->actor($request))]], 202);
    }

    /**
     * @return EloquentBuilder<Order>
     */
    private function filtered(Request $request): EloquentBuilder
    {
        $status = OrderStatus::tryFrom((string) $request->query('status'));
        $search = mb_trim((string) $request->query('q'));

        return Order::query()
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->when($search !== '', fn ($query) => $query->where(fn (Builder $inner) => $inner
                ->where('buyer_name', 'like', "%{$search}%")
                ->orWhere('buyer_email', 'like', '%'.mb_strtolower($search).'%')
                ->when(ctype_digit(mb_ltrim($search, '#')), fn ($number) => $number->orWhere('number', (int) mb_ltrim($search, '#')))))
            ->orderByDesc('number');
    }

    private function order(Request $request): Order
    {
        $id = $request->route('order');
        abort_unless(is_string($id), 404);

        return Order::query()->with('items')->whereKey($id)->firstOrFail();
    }

    private function actor(Request $request): ?string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_string($id) ? $id : null;
    }
}
