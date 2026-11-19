<?php

declare(strict_types=1);

namespace PHPeste\Payments\Http\Controllers;

use Illuminate\Http\JsonResponse;
use PHPeste\Community\Contracts\EditionDirectory;
use PHPeste\Payments\Contracts\PaymentAccounts;
use PHPeste\Payments\Data\ReceivingAccount;
use PHPeste\Payments\Enums\PaymentMethod;

class PublicReceiverController
{
    public function __invoke(string $edition, EditionDirectory $editions, PaymentAccounts $accounts): JsonResponse
    {
        abort_unless($editions->isPublished($edition), 404);

        $account = $accounts->active($edition);

        return response()->json(['data' => $account instanceof ReceivingAccount ? [
            'driver' => $account->driver,
            'public_key' => $account->publicKey,
            'holder_name' => $account->holderName,
            'holder_type' => $account->holderType->value,
            'methods' => array_map(fn (PaymentMethod $method): string => $method->value, $account->methods),
            'max_installments' => $account->maxInstallments,
            'pix_expiration_minutes' => $account->pixExpirationMinutes,
            'sandbox' => $account->environment->value === 'sandbox',
            'ready' => $account->ready,
        ] : null]);
    }
}
