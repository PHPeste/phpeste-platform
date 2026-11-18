<?php

declare(strict_types=1);

use PHPeste\Branding\Domains\Hostname;

it('normaliza o host antes de comparar', function (string $input, ?string $expected): void {
    expect(Hostname::normalize($input))->toBe($expected);
})->with([
    'maiúsculas e ponto final' => ['2027.PHPeste.dev.', '2027.phpeste.dev'],
    'com esquema, porta e caminho' => ['https://2027.phpeste.dev:443/programacao', '2027.phpeste.dev'],
    'domínio com acento' => ['phpeste-são-luís.com.br', 'xn--phpeste-so-lus-yhb8n.com.br'],
    'sem TLD' => ['localhost', null],
    'com espaço' => ['php este.dev', null],
    'injeção' => ['evil.com/@2027.phpeste.dev', 'evil.com'],
    'rótulo começando com hífen' => ['-x.phpeste.dev', null],
    'vazio' => ['', null],
]);
