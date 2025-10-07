<?php

declare(strict_types=1);

use Hyperf\AsyncQueue\Driver\RedisDriver;

return [
    'default' => [
        'driver' => RedisDriver::class,
        'channel' => 'withdraw_default',
        'redis' => [
            'pool' => 'default',
        ],
        'retry_seconds' => [5, 30, 60],
        'handle_timeout' => 15,
        'processes' => 1,
        'concurrent' => ['limit' => 2],
        // 'retry_limit' => 5,
        // 'timeout' => 2,
        'strict' => true,
    ],
];
