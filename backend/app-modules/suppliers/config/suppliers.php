<?php

declare(strict_types=1);

return [
    'attachments' => [
        'disk' => env('SUPPLIERS_ATTACHMENTS_DISK', 'local'),
        'max_kilobytes' => (int) env('SUPPLIERS_ATTACHMENTS_MAX_KB', 20_480),
        'url_minutes' => (int) env('SUPPLIERS_ATTACHMENTS_URL_MINUTES', 10),
    ],
];
