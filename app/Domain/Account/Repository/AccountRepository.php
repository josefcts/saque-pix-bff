<?php

declare(strict_types=1);

namespace App\Domain\Account\Repository;

use App\Domain\Account\Model\Account;
use Hyperf\Database\Query\Expression;

final class AccountRepository
{
    public function debitIfEnough(string $accountId, float $amount): bool
    {
        $affected = Account::query()
            ->where('id', $accountId)
            ->where('balance', '>=', $amount)
            ->update([
                'balance'    => new Expression('balance - ' . number_format($amount, 2, '.', '')),
                'updated_at' => new Expression('NOW()'),
            ]);

        return $affected > 0;
    }

    public function debitIfEnoughWithVersion(string $accountId, float $amount, int $expectedVersion): bool
    {
        $affected = Account::query()
            ->where('id', $accountId)
            ->where('version', $expectedVersion)
            ->where('balance', '>=', $amount)
            ->update([
                'balance'    => new Expression('balance - ' . number_format($amount, 2, '.', '')),
                'version'    => new Expression('version + 1'),
                'updated_at' => new Expression('NOW()'),
            ]);

        return $affected > 0;
    }
}
