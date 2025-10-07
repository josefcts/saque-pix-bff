<?php

declare(strict_types=1);

use App\Interface\Http\Controller\MetricsController;
use App\Interface\Http\Controller\WithdrawController;
use App\Application\Middleware\JwtMiddleware;
use Hyperf\HttpServer\Router\Router;
use App\Infrastructure\Auth\JwtService;

// ---------- Rotas públicas ----------
Router::get('/healthz', function () {
    return ['status' => 'ok', 'time' => date('c')];
});
Router::get('/', fn () => ['status' => 'ok']);
Router::get('/favicon.ico', fn () => '');
Router::get('/robots.txt', fn () => "User-agent: *\nDisallow: /\n");

Router::post('/auth/token', function () {
    $jwt = new JwtService();
    $token = $jwt->issueToken('test-user', ['role' => 'admin'], noExpiry: true);
    return ['token' => $token];
});

// ---------- Rotas protegidas por JWT ----------
Router::addGroup('', function () {
    // Exposta apenas com Authorization: Bearer <token>
    Router::get('/metrics', [MetricsController::class, 'scrape']);

    // Saque imediato/agendado
    Router::post('/account/{accountId}/balance/withdraw', [WithdrawController::class, 'create']);
}, ['middleware' => [JwtMiddleware::class]]);
