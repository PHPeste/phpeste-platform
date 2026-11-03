<?php

declare(strict_types=1);

arch('módulos não dependem de App\\')
    ->expect('PHPeste')
    ->not->toUse('App');

arch('módulos não têm débito técnico (dd, dump, var_dump)')
    ->expect('PHPeste')
    ->not->toUse(['dd', 'dump', 'var_dump', 'die']);
