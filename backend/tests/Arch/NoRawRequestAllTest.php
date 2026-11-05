<?php

declare(strict_types=1);

it('controllers nunca leem o payload bruto com $request->all() — sempre FormRequest ou Data object', function (): void {
    $controllerDirs = [
        __DIR__.'/../../app/Http/Controllers',
        ...(glob(__DIR__.'/../../app-modules/*/src/Http/Controllers', GLOB_ONLYDIR) ?: []),
    ];

    $offenders = [];

    foreach ($controllerDirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if ($contents !== false && preg_match('/\$request->all\(\)|request\(\)->all\(\)/', $contents) === 1) {
                $offenders[] = $file->getPathname();
            }
        }
    }

    expect($offenders)->toBe([]);
});
