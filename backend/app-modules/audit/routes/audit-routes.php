<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Audit\Http\Controllers\AuditLogController;

Route::apiV1Edition('audit')->get('/', AuditLogController::class)->name('audit.logs.index');
