<?php

declare(strict_types=1);

return [
    'http' => [
        Hyperf\Validation\Middleware\ValidationMiddleware::class,
        App\Middleware\PrometheusMiddleware::class,
        App\Middleware\LogPathMiddleware::class,
    ],
];
