<?php

declare(strict_types=1);

namespace PHPeste\Payments\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PHPeste\Payments\Models\PaymentAccount;

#[Signature('payments:reencrypt-credentials')]
#[Description('Regrava as credenciais dos gateways com a APP_KEY atual (rotação de chave)')]
class ReencryptCredentials extends Command
{
    public function handle(): int
    {
        $count = 0;

        PaymentAccount::query()->withoutGlobalScopes()->lazyById()->each(function (PaymentAccount $account) use (&$count): void {
            $account->forceFill(['credentials' => $account->credentials])->saveQuietly();
            $count++;
        });

        $this->info(sprintf('%d contas regravadas.', $count));

        return self::SUCCESS;
    }
}
