<?php

declare(strict_types=1);

// `expect('PHPeste\<Módulo>')` sozinho não desce para as subpastas de forma
// confiável neste setup (cada módulo é um pacote Composer com path-repository
// próprio, symlinkado — não um único root PSR-4 "PHPeste\"). Testado na prática:
// `not->toUse()` no namespace raiz do módulo não pega violação em Models/,
// mas pega normalmente quando aplicado a uma subpasta específica. Por isso
// iteramos módulo × subpasta real (as mesmas de backend/CLAUDE.md) abaixo.
$moduleSubfolders = ['Models', 'Actions', 'Data', 'Events', 'Listeners', 'Contracts', 'Http', 'Providers'];

foreach (glob(__DIR__.'/../../app-modules/*', GLOB_ONLYDIR) as $modulePath) {
    $module = str(basename($modulePath))->studly();
    $namespace = "PHPeste\\{$module}";

    foreach ($moduleSubfolders as $subfolder) {
        if (!is_dir("{$modulePath}/src/{$subfolder}")) {
            continue;
        }

        $subNamespace = "{$namespace}\\{$subfolder}";

        // App\Http\Resources, App\Http\Middleware, App\Support e App\Logging
        // são kernel compartilhado (ApiResource, ApiError, middlewares de
        // request) — todo módulo pode depender deles, como depende do
        // próprio Laravel. App\Models, App\Http\Controllers e App\Providers
        // são específicos da app raiz; nenhum módulo deveria importar daí.
        arch("{$module}\\{$subfolder} não depende de App\\Models, App\\Http\\Controllers ou App\\Providers")
            ->expect($subNamespace)
            ->not->toUse(['App\Models', 'App\Http\Controllers', 'App\Providers']);

        arch("{$module}\\{$subfolder} não tem débito técnico (dd, dump, var_dump)")
            ->expect($subNamespace)
            ->not->toUse(['dd', 'dump', 'var_dump', 'die']);
    }

    arch("Models de {$module} só são usados dentro do próprio módulo")
        ->expect("{$namespace}\\Models")
        ->toOnlyBeUsedIn([$namespace, 'Tests']);
}
