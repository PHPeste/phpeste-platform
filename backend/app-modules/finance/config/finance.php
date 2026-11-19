<?php

declare(strict_types=1);

return [
    'receipts' => [
        'disk' => env('FINANCE_RECEIPTS_DISK', 'local'),
        'max_kilobytes' => (int) env('FINANCE_RECEIPTS_MAX_KB', 10_240),
        'url_minutes' => (int) env('FINANCE_RECEIPTS_URL_MINUTES', 10),
    ],
];
