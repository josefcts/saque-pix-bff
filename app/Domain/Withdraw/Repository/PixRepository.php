<?php

declare(strict_types=1);

namespace App\Domain\Withdraw\Repository;

use App\Domain\Withdraw\Model\AccountWithdrawPix;
use Hyperf\Logger\LoggerFactory;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

final class PixRepository
{
    public function __construct(
        private LoggerFactory $loggerFactory,
    ) {
    }

    /**
     * Cria o registro PIX relacionado a um saque.
     *
     * @param string $withdrawId ID da tabela account_withdraw
     * @param array{
     *     key:string,
     *     type:string,
     *     provider?:string
     * } $pixData
     */
    public function store(string $withdrawId, array $pixData): string
    {
        $log = $this->loggerFactory->get('withdraw');
        $pixId = Uuid::uuid4()->toString();

        $data = [
            'id' => $pixId,
            'account_withdraw_id' => $withdrawId,
            'account_id' => $pixData['account_id'] ?? null,
            'key' => $pixData['key'],
            'type' => $pixData['type'],
            'status' => 'CREATED',
            'provider' => $pixData['provider'] ?? 'fitbank',
            'created_at' => Carbon::now('UTC'),
            'updated_at' => Carbon::now('UTC'),
        ];

        AccountWithdrawPix::query()->create($data);
        $log->info('PIX stored', ['pix_id' => $pixId, 'withdraw_id' => $withdrawId, 'key' => $pixData['key']]);

        return $pixId;
    }

    /**
     * Atualiza status e possíveis erros.
     */
    public function updateStatus(string $pixId, string $status, ?string $errorCode = null, ?string $tid = null): void
    {
        AccountWithdrawPix::query()
            ->where('id', $pixId)
            ->update([
                'status' => $status,
                'error_code' => $errorCode,
                'tid' => $tid,
                'updated_at' => Carbon::now('UTC'),
            ]);

        $this->loggerFactory->get('withdraw')->info('PIX updated', [
            'pix_id' => $pixId,
            'status' => $status,
            'error_code' => $errorCode,
        ]);
    }
}
