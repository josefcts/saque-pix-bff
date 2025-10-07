<?php

declare(strict_types=1);

namespace App\Application\DTO;

final class WithdrawRequestDTO
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $method,
        public readonly float $amount,
        public readonly ?array $pix = null,
        public readonly ?\DateTimeInterface $schedule = null,
    ) {
    }
}
