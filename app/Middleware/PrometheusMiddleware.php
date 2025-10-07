<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\HttpServer\Router\Dispatched;
use Prometheus\CollectorRegistry;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PrometheusMiddleware implements MiddlewareInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() === '/metrics') {
            return $handler->handle($request);
        }

        /** @var CollectorRegistry $registry */
        $registry = $this->container->get(CollectorRegistry::class);

        // Labels
        $method = $request->getMethod();
        $route  = $this->resolveRoutePattern($request); // reduz cardinalidade
        $status = '200';

        // Timers (em segundos)
        $start = microtime(true);

        try {
            $response = $handler->handle($request);
            $status   = (string) $response->getStatusCode();
            return $response;
        } finally {
            $duration = microtime(true) - $start;

            // Counter de requisições
            $counter = $registry->getOrRegisterCounter(
                'http',
                'requests_total',
                'HTTP requests total',
                ['method','route','status']
            );
            $counter->inc([$method, $route, $status]);

            // Histograma de duração
            $histogram = $registry->getOrRegisterHistogram(
                'http',
                'request_duration_seconds',
                'HTTP request duration in seconds',
                // 4º parâmetro: NOME das labels (strings)
                ['method', 'route', 'status'],
                // 5º parâmetro: buckets (floats)
                [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5]
            );
            $histogram->observe($duration, [$method, $route, $status]);
        }
    }

    private function resolveRoutePattern(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();

        /** @var Dispatched|null $dispatched */
        $dispatched = $request->getAttribute(Dispatched::class);
        if ($dispatched && $dispatched->handler && isset($dispatched->handler->route)) {
            $pattern = (string) $dispatched->handler->route;
            if ($pattern !== '') {
                return $pattern;
            }
        }

        return $path;
    }
}
