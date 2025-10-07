<?php

declare(strict_types=1);

namespace App\Domain\Withdraw\Repository;

use App\Application\DTO\WithdrawRequestDTO;
use App\Domain\Withdraw\Model\AccountWithdraw;
use Carbon\Carbon;
use DateTimeInterface;
use Ramsey\Uuid\Uuid;

final class WithdrawRepository
{
    /**
     * Timezone de origem dos horários recebidos pela API/UI.
     * Se o DTO já vier com timezone embutido (DateTimeInterface com tz),
     * este valor é ignorado. Ajuste conforme sua entrada.
     */
    private const INPUT_TZ = 'America/Sao_Paulo';

    public function createScheduled(WithdrawRequestDTO $dto, ?string $requestId): string
    {
        $id = Uuid::uuid4()->toString();

        $scheduledUtc = $this->toUtc($dto->schedule);

        AccountWithdraw::query()->create([
            'id'            => $id,
            'account_id'    => $dto->accountId,
            'method'        => $dto->method,
            'amount'        => $dto->amount,
            'version'       => 1,
            'scheduled_for' => $scheduledUtc,   // sempre UTC
            'done'          => false,
            'error'         => false,
            'error_code'    => null,
            'request_id'    => $requestId,
        ]);

        return $id;
    }

    public function createImmediate(string $accountId, string $method, float $amount, ?string $errorCode = null): string
    {
        $id = Uuid::uuid4()->toString();

        AccountWithdraw::query()->create([
            'id'            => $id,
            'account_id'    => $accountId,
            'method'        => $method,
            'amount'        => $amount,
            'version'       => 1,
            'scheduled_for' => null,
            'done'          => true,
            'error'         => (bool) $errorCode,
            'error_code'    => $errorCode,
            'request_id'    => null,
        ]);

        return $id;
    }

    public function markDone(string $id): void
    {
        AccountWithdraw::query()
            ->where('id', $id)
            ->update([
                'done'       => true,
                'error'      => false,
                'error_code' => null,
                'updated_at' => Carbon::now('UTC'),
            ]);
    }

    public function markFailed(string $id, string $errorCode): void
    {
        AccountWithdraw::query()
            ->where('id', $id)
            ->update([
                'done'       => true,
                'error'      => true,
                'error_code' => $errorCode,
                'updated_at' => Carbon::now('UTC'),
            ]);
    }

    /**
     * Converte qualquer entrada (string ou DateTimeInterface) para Carbon UTC.
     * Regras:
     * - Se vier DateTimeInterface, respeita a timezone embutida; só converte para UTC.
     * - Se vier string SEM timezone, assume INPUT_TZ (America/Sao_Paulo) e converte para UTC.
     * - Se vier string COM timezone (ex.: 2025-10-06T10:00:00-03:00), respeita e converte para UTC.
     */
    private function toUtc(string|DateTimeInterface $when): Carbon
    {
        if ($when instanceof DateTimeInterface) {
            return Carbon::instance($when)->utc();
        }

        // String: tenta parsear preservando tz se existir; caso não exista, assume INPUT_TZ
        // Carbon::parse($str, tz) usa tz default apenas se a string não tiver offset embutido.
        return Carbon::parse($when, self::INPUT_TZ)->utc();
    }
}
