<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Infrastructure\Auth\JwtService;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\HttpServer\Contract\RequestInterface as HttpRequest;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class JwtMiddleware implements MiddlewareInterface
{
    public function __construct(
        private HttpResponse $response,
        private JwtService $jwt
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var HttpRequest $req */
        $req = $request;
        $header = $req->getHeaderLine('Authorization');

        if (! $header || ! str_starts_with($header, 'Bearer ')) {
            return $this->unauthorized('Missing Bearer token');
        }

        $token = trim(substr($header, 7));

        try {
            $claims = $this->jwt->decodeToken($token);
            // injeta no request como atributo
            $request = $request->withAttribute('jwt', $claims);
        } catch (\Throwable $e) {
            return $this->unauthorized('Invalid or expired token');
        }

        return $handler->handle($request);
    }

    private function unauthorized(string $message): ResponseInterface
    {
        return $this->response
            ->json(['error' => 'unauthorized', 'message' => $message])
            ->withStatus(401);
    }
}
