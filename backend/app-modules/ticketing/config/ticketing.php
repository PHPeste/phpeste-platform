<?php

declare(strict_types=1);

return [
    'qr_key' => env('TICKETING_QR_KEY'),

    'imports' => [
        'disk' => env('TICKETING_IMPORT_DISK', 'local'),
        'max_rows' => (int) env('TICKETING_IMPORT_MAX_ROWS', 20_000),
        'max_kilobytes' => (int) env('TICKETING_IMPORT_MAX_KB', 10_240),
        'chunk_size' => (int) env('TICKETING_IMPORT_CHUNK_SIZE', 200),
    ],
];
