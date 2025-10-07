<?php

declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

return [
    'default' => [
        'handlers' => [
            'stdout' => [
                'class' => StreamHandler::class,
                'constructor' => ['stream' => 'php://stdout', 'level' => Logger::INFO],
            ],
        ],
    ],
    'channels' => [
        'withdraw' => ['handlers' => ['stdout']],
        'cron'     => ['handlers' => ['stdout']],
    ],
];
