<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->printer()->compact();

pest()->extend(TestCase::class)
    ->group('unit')
    ->in('Unit', '../app-modules/*/tests/Unit');

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->group('feature')
    ->in('Feature', '../app-modules/*/tests/Feature');

pest()->extend(TestCase::class)
    ->group('arch')
    ->in('Arch', '../app-modules/*/tests/Arch');
