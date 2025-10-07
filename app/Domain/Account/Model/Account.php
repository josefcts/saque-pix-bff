<?php

declare(strict_types=1);

namespace App\Domain\Account\Model;

use Hyperf\DbConnection\Model\Model;

class Account extends Model
{
    protected ?string $table = 'accounts';
    public bool $incrementing = false;
    protected string $keyType = 'string';

    protected array $fillable = [
        'id', 'balance', 'version',
    ];

    protected array $casts = [
        'balance' => 'decimal:2',
        'version' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
