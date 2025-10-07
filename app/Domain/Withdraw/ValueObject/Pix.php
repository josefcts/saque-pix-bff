<?php

declare(strict_types=1);

namespace App\Domain\Withdraw\ValueObject;

final class Pix
{
    public function __construct(
        public string $type,
        public string $key,
    ) {
    }
}
