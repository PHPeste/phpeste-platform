<?php

declare(strict_types=1);

namespace PHPeste\Payments\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Payments\Models\PaymentAccount;

class UpdatePaymentAccount
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(PaymentAccount $account, array $attributes): PaymentAccount
    {
        if (!$account->isActive()) {
            throw ValidationException::withMessages(['account' => 'Esta conta foi substituída e fica só para consulta e reembolsos.']);
        }

        $account->update($attributes);

        return $account;
    }
}
