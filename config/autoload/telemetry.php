<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'otel' => [
        'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://otel-collector:4317'),
        'service_name' => env('APP_NAME', 'PixWithdraw'),
    ],
];
