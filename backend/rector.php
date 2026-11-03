<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
        __DIR__.'/app-modules/*/src',
        __DIR__.'/app-modules/*/config',
        __DIR__.'/app-modules/*/database',
        __DIR__.'/app-modules/*/routes',
        __DIR__.'/app-modules/*/tests',
    ])
    ->withSkip([
        __DIR__.'/bootstrap/cache',
    ])
    ->withCache(cacheDirectory: __DIR__.'/.cache/rector', cacheClass: FileCacheStorage::class)
    ->withImportNames()
    ->withRootFiles()
    ->withPhpSets(php84: true)
    ->withComposerBased(laravel: true)
    ->withBootstrapFiles([__DIR__.'/vendor/larastan/larastan/bootstrap.php'])
    ->withPHPStanConfigs([__DIR__.'/phpstan.neon'])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        carbon: true,
    )
    ->withSets([
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_IF_HELPERS,
        LaravelSetList::LARAVEL_TYPE_DECLARATIONS,
    ]);
