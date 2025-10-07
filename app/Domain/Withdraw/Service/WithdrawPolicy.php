<?php

declare(strict_types=1);

namespace App\Domain\Withdraw\Service;

use App\Application\DTO\WithdrawRequestDTO;

final class WithdrawPolicy
{
    public function assert(WithdrawRequestDTO $dto): void
    {
        if ($dto->method !== 'PIX') {
            throw new \InvalidArgumentException('UNSUPPORTED_METHOD');
        }
        if ($dto->amount <= 0) {
            throw new \InvalidArgumentException('NEGATIVE_AMOUNT');
        }
        if (!isset($dto->pix['type'], $dto->pix['key']) || $dto->pix['type'] !== 'email') {
            throw new \InvalidArgumentException('INVALID_PIX');
        }
        if (!filter_var($dto->pix['key'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('INVALID_PIX');
        }
        if (!$dto->isImmediate()) {
            $ts = strtotime($dto->schedule ?? '');
            if (!$ts) {
                throw new \InvalidArgumentException('INVALID_SCHEDULE');
            }
            $now = time();
            $max = $now + 7 * 24 * 3600;
            if ($ts <= $now) {
                throw new \InvalidArgumentException('INVALID_SCHEDULE');
            }
            if ($ts > $max) {
                throw new \InvalidArgumentException('INVALID_SCHEDULE');
            }
        }
    }
}
