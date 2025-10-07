<?php
declare(strict_types=1);

namespace App\Domain\Withdraw\Entity;

final class Withdraw
{
    public function __construct(
        public string $id,
        public string $accountId,
        public string $method,
        public float $amount,
        public bool $scheduled,
        public ?string $scheduledFor,
        public bool $done,
        public bool $error,
        public ?string $errorReason,
        public ?string $requestId,
    ) {}
}
