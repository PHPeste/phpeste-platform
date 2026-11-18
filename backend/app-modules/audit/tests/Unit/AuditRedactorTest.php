<?php

declare(strict_types=1);

use PHPeste\Audit\Support\AuditRedactor;

it('remove segredos por inteiro, inclusive aninhados e por sufixo', function (): void {
    $redacted = AuditRedactor::redact([
        'api_key' => '$aact_YTU5YTE0M2M2N2I4MTliNzk0YTI5N2U5MzdjNWZmNDQ6OjAwMDAwMDAwMDAwMDAwNjMxNTY6OiRhYWNoXzI3',
        'gateway' => ['webhook_secret' => 'whsec_123456789', 'access_token' => 'APP_USR-1234', 'environment' => 'production'],
        'asaas_api_key' => 'chave-do-asaas',
        'password' => 'umasenhabemforte123',
    ]);

    expect($redacted)->toBe([
        'api_key' => '[removido]',
        'gateway' => ['webhook_secret' => '[removido]', 'access_token' => '[removido]', 'environment' => 'production'],
        'asaas_api_key' => '[removido]',
        'password' => '[removido]',
    ]);
});

it('a chave do gateway não aparece nem em parte nem de forma reversível', function (): void {
    $key = '$aact_YTU5YTE0M2M2N2I4MTliNzk0YTI5N2U5MzdjNWZmNDQ6OjAwMDAwMDAwMDAwMDAwNjMxNTY6OiRhYWNoXzI3';
    $json = (string) json_encode(AuditRedactor::redact(['before' => ['api_key' => $key], 'after' => ['api_key' => strrev($key)]]));

    expect($json)->not->toContain(mb_substr($key, -6))
        ->not->toContain(mb_substr($key, 0, 10))
        ->not->toContain(hash('sha256', $key))
        ->not->toContain(base64_encode($key))
        ->not->toContain(mb_substr(strrev($key), 0, 10));
});

it('mascara dados pessoais mantendo só o suficiente para reconhecer', function (): void {
    expect(AuditRedactor::redact(['email' => 'ana.martins@example.com', 'buyer_cpf' => '123.456.789-09', 'phone' => '(83) 99999-1234', 'name' => 'Ana']))
        ->toBe(['email' => 'a***@example.com', 'buyer_cpf' => '***09', 'phone' => '***34', 'name' => 'Ana']);
});

it('não mexe no que não é sensível e aceita nulo', function (): void {
    expect(AuditRedactor::redact(['role' => 'financeiro', 'from' => null]))->toBe(['role' => 'financeiro', 'from' => null])
        ->and(AuditRedactor::redact(null))->toBeNull();
});
