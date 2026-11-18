<?php

declare(strict_types=1);

use PHPeste\Notifications\Mail\EditionDates;
use PHPeste\Notifications\Mail\TemplateRenderer;

it('encontra as variáveis usadas no texto', function (): void {
    expect(TemplateRenderer::placeholders('Oi, {nome}! Pedido {pedido} do {nome}. {{ $x }} {Nome}'))->toBe(['nome', 'pedido']);
});

it('substitui só variáveis conhecidas e mantém o resto como está', function (): void {
    expect(TemplateRenderer::plain('Oi, {nome}! {desconhecida}', ['nome' => 'Ada']))->toBe('Oi, Ada! {desconhecida}');
});

it('escapa HTML do modelo e dos valores, sem executar nada', function (): void {
    $paragraphs = TemplateRenderer::htmlParagraphs(
        "Oi, {nome}!<script>alert(1)</script>\n\n<a href=\"javascript:x()\">clique</a> {{ phpinfo() }} @php echo 1; @endphp",
        ['nome' => '<img src=x onerror=alert(1)>'],
    );

    $html = implode('', $paragraphs);

    expect($paragraphs)->toHaveCount(2)
        ->and($html)->not->toContain('<script', '<img', '<a ')
        ->toContain('&lt;script&gt;', '&lt;img src=x onerror=alert(1)&gt;', '{{ phpinfo() }}', '@php echo 1;');
});

it('separa parágrafos por linha em branco e quebra linhas simples', function (): void {
    expect(TemplateRenderer::htmlParagraphs("linha 1\nlinha 2\n\nsegundo", []))->toBe(['linha 1<br>'."\n".'linha 2', 'segundo']);
});

it('formata as datas da edição em português', function (?string $starts, ?string $ends, string $expected): void {
    expect(EditionDates::format($starts, $ends))->toBe($expected);
})->with([
    'dias seguidos' => ['2027-10-08', '2027-10-09', '8 e 9 de outubro'],
    'intervalo no mês' => ['2027-10-08', '2027-10-10', '8 a 10 de outubro'],
    'um dia' => ['2027-10-08', '2027-10-08', '8 de outubro'],
    'virando o mês' => ['2027-09-30', '2027-10-02', '30 de setembro a 2 de outubro'],
    'sem data' => [null, null, 'data a definir'],
]);
