<?php

declare(strict_types=1);

namespace App\Infra\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;
use Prometheus\RenderTextFormat;

use function Hyperf\Support\env;

final class PrometheusFactory
{
    public static function makeRegistry(): CollectorRegistry
    {
        $adapter = new Redis([
            'host'                   => env('PROM_REDIS_HOST', env('REDIS_HOST', '127.0.0.1')),
            'port'                   => (int) env('PROM_REDIS_PORT', (int) env('REDIS_PORT', 6379)),
            'password'               => env('PROM_REDIS_AUTH', env('REDIS_AUTH', null)) ?: null,
            'timeout'                => 0.1,
            'read_timeout'           => 0.1,
            'persistent_connections' => false,
            'database'               => (int) env('PROM_REDIS_DB', (int) env('REDIS_DB', 0)),
        ]);

        return new CollectorRegistry($adapter, true);
    }

    public static function makeRenderer(): RenderTextFormat
    {
        return new RenderTextFormat();
    }
}
