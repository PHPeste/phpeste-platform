<?php

declare(strict_types=1);

namespace PHPeste\Payments\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Payments\Events\PaymentAccountVerified;
use PHPeste\Payments\Gateway\GatewayRegistry;
use PHPeste\Payments\Models\PaymentAccount;
use Throwable;

class VerifyPaymentAccount
{
    public function __construct(private readonly GatewayRegistry $gateways) {}

    public function handle(PaymentAccount $account, ?string $actorId): PaymentAccount
    {
        if (!$account->isActive()) {
            throw ValidationException::withMessages(['account' => 'Esta conta foi substituída e fica só para consulta e reembolsos.']);
        }

        try {
            $result = $this->gateways->driver($account->driver)->verify($account->gatewayCredentials());
        } catch (Throwable $throwable) {
            report($throwable);

            throw ValidationException::withMessages(['api_key' => 'Não foi possível falar com o gateway agora. Tente de novo em alguns minutos.']);
        }

        if (!$result->valid) {
            $account->update(['verified_at' => null]);

            throw ValidationException::withMessages(['api_key' => $result->message ?? 'O gateway recusou as chaves.']);
        }

        $account->update(['verified_at' => now()]);
        event(new PaymentAccountVerified($account->edition_id, $account->id, 'credentials', $actorId));

        return $account;
    }
}
