<?php

declare(strict_types=1);

namespace App\Domain\Account\Entity;

final class Account
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public float $balance,
        public int $version,
    ) {
    }
}
