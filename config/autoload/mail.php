<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'default' => [
        'mailer' => env('MAIL_MAILER', 'smtp'),
        'host' => env('MAIL_HOST', 'localhost'),
        'port' => (int) env('MAIL_PORT', 1025),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'encryption' => env('MAIL_ENCRYPTION', null),
        'from' => [
            'address' => env('MAIL_FROM_ADDRESS', 'no-reply@local.test'),
            'name' => env('MAIL_FROM_NAME', 'Pix Withdraw'),
        ],
    ],
];
