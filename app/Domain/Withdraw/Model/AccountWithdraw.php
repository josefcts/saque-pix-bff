<?php

declare(strict_types=1);

namespace App\Domain\Withdraw\Model;

use Hyperf\DbConnection\Model\Model;

class AccountWithdraw extends Model
{
    protected ?string $table = 'account_withdraw';
    public bool $incrementing = false;
    protected string $keyType = 'string';

    protected array $fillable = [
        'id', 'account_id', 'method', 'amount',
        'version', 'scheduled_for', 'queued_at',
        'done', 'error', 'error_code', 'request_id',
    ];

    protected array $casts = [
        'amount'        => 'decimal:2',
        'version'       => 'int',
        'scheduled_for' => 'datetime',
        'queued_at'     => 'datetime',
        'done'          => 'bool',
        'error'         => 'bool',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];
}
