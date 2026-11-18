<?php

declare(strict_types=1);

use PHPeste\Community\Enums\EditionStatus;

it('permite só as transições previstas no ciclo da edição', function (EditionStatus $from, EditionStatus $to, bool $allowed): void {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'rascunho → CFP aberto' => [EditionStatus::Draft, EditionStatus::CfpOpen, true],
    'rascunho → vendas abertas' => [EditionStatus::Draft, EditionStatus::SalesOpen, true],
    'CFP aberto → vendas abertas' => [EditionStatus::CfpOpen, EditionStatus::SalesOpen, true],
    'vendas abertas → em andamento' => [EditionStatus::SalesOpen, EditionStatus::Ongoing, true],
    'em andamento → encerrada' => [EditionStatus::Ongoing, EditionStatus::Closed, true],
    'rascunho → encerrada' => [EditionStatus::Draft, EditionStatus::Closed, false],
    'rascunho → em andamento' => [EditionStatus::Draft, EditionStatus::Ongoing, false],
    'CFP aberto → rascunho' => [EditionStatus::CfpOpen, EditionStatus::Draft, false],
    'vendas abertas → CFP aberto' => [EditionStatus::SalesOpen, EditionStatus::CfpOpen, false],
    'encerrada → em andamento' => [EditionStatus::Closed, EditionStatus::Ongoing, false],
    'rascunho → rascunho' => [EditionStatus::Draft, EditionStatus::Draft, false],
]);

it('considera publicada toda edição fora de rascunho', function (): void {
    expect(EditionStatus::Draft->isPublished())->toBeFalse();

    foreach ([EditionStatus::CfpOpen, EditionStatus::SalesOpen, EditionStatus::Ongoing, EditionStatus::Closed] as $status) {
        expect($status->isPublished())->toBeTrue();
    }
});
