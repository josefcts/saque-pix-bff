<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LogPathMiddleware implements MiddlewareInterface
{
    protected $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->logger = $container->get(LoggerFactory::class)->get('request');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path   = $request->getUri()->getPath();
        $method = $request->getMethod();

        $this->logger->info(sprintf('[Request] %s %s', $method, $path));

        return $handler->handle($request);
    }
}
