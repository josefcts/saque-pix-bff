<?php

declare(strict_types=1);

namespace App\Interface\Http\Controller;

use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

final class MetricsController
{
    public function __construct(
        private CollectorRegistry $registry,
        private RenderTextFormat $renderer,
        private HttpResponse $response, // 👈 usa a abstração do Hyperf
    ) {
    }

    public function scrape()
    {
        $metrics = $this->registry->getMetricFamilySamples();
        $body    = $this->renderer->render($metrics);

        return $this->response
            ->raw($body)
            ->withHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }
}
