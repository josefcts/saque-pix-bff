<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

use function Hyperf\Support\env;

final class JwtService
{
    private string $secret;
    private string $algo;
    private int $ttl;
    private bool $noExpiry;

    public function __construct(
        ?string $secret = null,
        string $algo = 'HS256',
        int $ttl = 3600,
        bool $noExpiry = false
    ) {
        $this->secret = $secret ?: (env('JWT_SECRET') ?: 'changeme');
        $this->algo   = env('JWT_ALGO') ?: $algo;
        $this->ttl    = (int) (env('JWT_TTL') ?: $ttl);
        $this->noExpiry = (bool) (env('JWT_NO_EXPIRY') ?: $noExpiry);
    }

    /**
     * Emite token JWT.
     *  - $subject = identificador (ex: user_id)
     *  - $claims = claims extras
     *  - $noExpiry = se true, não adiciona campo "exp"
     */
    public function issueToken(string $subject, array $claims = [], bool $noExpiry = null): string
    {
        $noExpiry = $noExpiry ?? $this->noExpiry;

        $now = time();
        $payload = array_merge($claims, [
            'sub' => $subject,
            'iat' => $now,
            'nbf' => $now,
        ]);

        if (! $noExpiry) {
            $payload['exp'] = $now + $this->ttl;
        }

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    public function decodeToken(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secret, $this->algo));
        return (array) $decoded;
    }
}
