<?php

declare(strict_types=1);

return [
    'attachments' => [
        'disk' => env('SPONSORS_ATTACHMENTS_DISK', 'local'),
        'max_kilobytes' => (int) env('SPONSORS_ATTACHMENTS_MAX_KB', 20_480),
        'url_minutes' => (int) env('SPONSORS_ATTACHMENTS_URL_MINUTES', 10),
    ],
];
