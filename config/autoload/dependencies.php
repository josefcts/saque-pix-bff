<?php

declare(strict_types=1);

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use App\Infra\Metrics\PrometheusFactory;

return [
    CollectorRegistry::class => function () {
        return PrometheusFactory::makeRegistry();
    },
    RenderTextFormat::class => function () {
        return PrometheusFactory::makeRenderer();
    },
];
