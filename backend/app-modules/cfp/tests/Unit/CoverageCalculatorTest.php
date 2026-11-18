<?php

declare(strict_types=1);

use PHPeste\Cfp\Decisions\CoverageCalculator;

it('sem ninguém aprovado não inventa percentual', function (): void {
    expect(CoverageCalculator::calculate([]))->toMatchArray([
        'people' => 0,
        'nordeste_percent' => null,
        'nordeste_states_missing' => ['AL', 'BA', 'CE', 'MA', 'PB', 'PE', 'PI', 'RN', 'SE'],
    ]);
});

it('estado único do Nordeste: 100% e os outros oito estados faltando', function (): void {
    $coverage = CoverageCalculator::calculate(['a' => 'PB', 'b' => 'PB', 'c' => 'PB']);

    expect($coverage['nordeste_percent'])->toBe(100)
        ->and($coverage['by_state'])->toBe(['PB' => 3])
        ->and($coverage['nordeste_states_missing'])->toBe(['AL', 'BA', 'CE', 'MA', 'PE', 'PI', 'RN', 'SE']);
});

it('estado único fora do Nordeste: 0%', function (): void {
    expect(CoverageCalculator::calculate(['a' => 'SP', 'b' => 'SP'])['nordeste_percent'])->toBe(0);
});

it('arredonda para baixo, para nunca dar a meta como batida sem estar', function (): void {
    expect(CoverageCalculator::calculate(['a' => 'PB', 'b' => 'CE', 'c' => 'SP'])['nordeste_percent'])->toBe(66)
        ->and(CoverageCalculator::calculate(['a' => 'PB', 'b' => 'SP'])['nordeste_percent'])->toBe(50);
});

it('pessoa sem estado conta no total, mas não como Nordeste', function (): void {
    $coverage = CoverageCalculator::calculate(['a' => 'PB', 'b' => null, 'c' => 'XX']);

    expect($coverage['people'])->toBe(3)
        ->and($coverage['unknown_state'])->toBe(2)
        ->and($coverage['nordeste_percent'])->toBe(33);
});
