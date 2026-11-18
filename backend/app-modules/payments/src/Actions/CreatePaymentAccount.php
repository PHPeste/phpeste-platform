<?php

declare(strict_types=1);

namespace PHPeste\Payments\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPeste\Payments\Contracts\AccountUsage;
use PHPeste\Payments\Events\PaymentAccountChanged;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Payments\Rules\TaxDocument;

class CreatePaymentAccount
{
    public function __construct(private readonly AccountUsage $usage) {}

    /**
     * @param  array{driver: string, environment: string, api_key: string, webhook_secret: string, holder_type: string, holder_name: string, holder_document: string, methods: list<string>, max_installments: int, pix_expiration_minutes: int}  $data
     */
    public function handle(string $editionId, array $data, bool $confirmed, ?string $actorId): PaymentAccount
    {
        $current = PaymentAccount::query()->forEdition($editionId)->whereNull('retired_at')->latest()->first();
        $paid = $current === null ? 0 : $this->usage->paidOrders($editionId, $current->id);

        if ($paid > 0 && !$confirmed) {
            throw ValidationException::withMessages(['confirm' => sprintf(
                'Esta conta já recebeu %d %s. A conta atual fica guardada só para reembolsos desses pedidos, e as próximas vendas caem na conta nova. Confirme para trocar.',
                $paid,
                $paid === 1 ? 'pedido pago' : 'pedidos pagos',
            )]);
        }

        return DB::transaction(function () use ($editionId, $data, $current, $actorId): PaymentAccount {
            if ($current !== null) {
                PaymentAccount::query()->forEdition($editionId)->whereKey($current->id)->update(['retired_at' => now()]);
            }

            $account = PaymentAccount::create([
                'edition_id' => $editionId,
                'driver' => $data['driver'],
                'environment' => $data['environment'],
                'credentials' => ['api_key' => $data['api_key'], 'webhook_secret' => $data['webhook_secret']],
                'credentials_hint' => mb_substr($data['api_key'], -4),
                'holder_type' => $data['holder_type'],
                'holder_name' => $data['holder_name'],
                'holder_document_masked' => TaxDocument::mask($data['holder_document']),
                'methods' => $data['methods'],
                'max_installments' => $data['max_installments'],
                'pix_expiration_minutes' => $data['pix_expiration_minutes'],
                'webhook_token' => Str::random(48),
                'created_by' => $actorId,
            ]);

            event(new PaymentAccountChanged(
                $editionId,
                $account->id,
                $current === null ? null : $this->snapshot($current),
                $this->snapshot($account),
                $actorId,
            ));

            return $account;
        });
    }

    /**
     * @return array<string, string>
     */
    private function snapshot(PaymentAccount $account): array
    {
        return [
            'account_id' => $account->id,
            'driver' => $account->driver,
            'environment' => $account->environment->value,
            'holder_type' => $account->holder_type->value,
            'holder_name' => $account->holder_name,
            'credentials_hint' => '••••'.$account->credentials_hint,
        ];
    }
}
