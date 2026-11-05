<?php

declare(strict_types=1);

return [
    'rate_limits' => [
        'login' => [
            'attempts' => (int) env('RATE_LIMIT_LOGIN_ATTEMPTS', 5),
            'decay_minutes' => (int) env('RATE_LIMIT_LOGIN_DECAY_MINUTES', 1),
        ],
        'password_reset' => [
            'attempts' => (int) env('RATE_LIMIT_PASSWORD_RESET_ATTEMPTS', 5),
            'decay_minutes' => (int) env('RATE_LIMIT_PASSWORD_RESET_DECAY_MINUTES', 1),
        ],
        'checkout' => [
            'attempts' => (int) env('RATE_LIMIT_CHECKOUT_ATTEMPTS', 10),
            'decay_minutes' => (int) env('RATE_LIMIT_CHECKOUT_DECAY_MINUTES', 1),
        ],
        'webhooks' => [
            'attempts' => (int) env('RATE_LIMIT_WEBHOOKS_ATTEMPTS', 60),
            'decay_minutes' => (int) env('RATE_LIMIT_WEBHOOKS_DECAY_MINUTES', 1),
        ],
    ],
];
