<?php

declare(strict_types=1);

namespace App\Domain\Withdraw\Model;

use Hyperf\DbConnection\Model\Model;
use Ramsey\Uuid\Uuid;

final class AccountWithdrawPix extends Model
{
    protected ?string $table = 'account_withdraw_pix';
    public bool $incrementing = false;
    protected string $keyType = 'string';

    protected array $fillable = [
        'id',
        'account_withdraw_id',
        'account_id',
        'type',
        'key',
        'status',
        'provider',
        'error_code',
        'tid',
        'confirmed_at',
    ];

    protected array $casts = [
        'confirmed_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    /**
     * Gera UUID automaticamente antes de inserir.
     */
    protected static function creatingModel(self $model): void
    {
        if (empty($model->id)) {
            $model->id = Uuid::uuid4()->toString();
        }
    }
}
