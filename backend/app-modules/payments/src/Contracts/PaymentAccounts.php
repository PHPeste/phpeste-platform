<?php

declare(strict_types=1);

namespace PHPeste\Payments\Contracts;

use PHPeste\Payments\Data\ReceivingAccount;

interface PaymentAccounts
{
    public function active(string $editionId): ?ReceivingAccount;

    public function ready(string $editionId): bool;

    /**
     * Todas as contas que a edição já usou, inclusive as trocadas, para a
     * receita sair separada por quem recebeu.
     *
     * @return list<ReceivingAccount>
     */
    public function all(string $editionId): array;
}
