<?php

declare(strict_types=1);

arch('módulos não dependem de App\\')
    ->expect('PHPeste')
    ->not->toUse('App');

arch('módulos não têm débito técnico (dd, dump, var_dump)')
    ->expect('PHPeste')
    ->not->toUse(['dd', 'dump', 'var_dump', 'die']);

foreach (glob(__DIR__.'/../../app-modules/*', GLOB_ONLYDIR) as $modulePath) {
    $module = str(basename($modulePath))->studly();
    $namespace = "PHPeste\\{$module}";

    arch("Models de {$module} só são usados dentro do próprio módulo")
        ->expect("{$namespace}\\Models")
        ->toOnlyBeUsedIn([$namespace, 'Tests']);
}
