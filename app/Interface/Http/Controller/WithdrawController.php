<?php

declare(strict_types=1);

namespace App\Interface\Http\Controller;

use App\Application\DTO\WithdrawRequestDTO;
use App\Application\Service\WithdrawService;
use App\Interface\Http\Request\WithdrawRequest;
use Hyperf\HttpServer\Contract\ResponseInterface;

final class WithdrawController
{
    public function __construct(private WithdrawService $service)
    {
    }

    public function create(string $accountId, WithdrawRequest $request, ResponseInterface $response)
    {
        $validated = $request->validated();

        $dto = new WithdrawRequestDTO(
            accountId: $accountId,
            method: $validated['method'],
            amount: (float) $validated['amount'],
            schedule: isset($validated['schedule'])
                ? new \DateTimeImmutable($validated['schedule'])
                : null,
            pix: [
                'key' => $validated['pix']['key'],
                'type' => $validated['pix']['type'],
                'provider' => $validated['pix']['provider'] ?? 'fitbank',
            ]
        );

        $result = $this->service->requestWithdraw($dto);

        return $response->json($result)->withStatus(201);
    }
}
