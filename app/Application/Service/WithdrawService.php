<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\WithdrawRequestDTO;
use App\Domain\Account\Repository\AccountRepository;
use App\Domain\Withdraw\Repository\PixRepository;
use App\Domain\Withdraw\Repository\WithdrawRepository;
use App\Infrastructure\Mail\SimpleSmtpMailer;
use App\Infrastructure\Mail\View;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use Hyperf\Logger\LoggerFactory;

final class WithdrawService
{
    public function __construct(
        private AccountRepository $accountRepo,
        private WithdrawRepository $withdrawRepo,
        private PixRepository $pixRepo,
    ) {
    }

    /**
     * Orquestra o fluxo de saque:
     *  - Se schedule == null => imediato (com e-mail imediato pós-commit)
     *  - Se schedule != null => agendado
     */
    public function requestWithdraw(WithdrawRequestDTO $dto, ?string $idempotencyKey = null): array
    {
        if ($dto->schedule === null) {
            return $this->processImmediate($dto, $idempotencyKey);
        }

        $withdrawId = $this->withdrawRepo->createScheduled($dto, $idempotencyKey);

        if ($dto->method === 'pix' && $dto->pix) {
            $pixData = [
                'account_id' => $dto->accountId,
                'key' => $dto->pix['key'],
                'type' => $dto->pix['type'],
                'provider' => $dto->pix['provider'] ?? 'fitbank',
            ];
            $this->pixRepo->store($withdrawId, $pixData);
        }

        return ['withdraw_id' => $withdrawId, 'status' => 'scheduled'];
    }

    /**
     * Saque imediato: débito e registro (PIX opcional) + e-mail imediato pós-commit.
     */
    private function processImmediate(WithdrawRequestDTO $dto, ?string $idempotencyKey = null): array
    {
        // 1) Executa a transação e captura os dados necessários para notificar após o commit
        $result = Db::transaction(function () use ($dto, $idempotencyKey) {
            $ok = $this->accountRepo->debitIfEnough($dto->accountId, $dto->amount);

            // saldo insuficiente
            if (! $ok) {
                $withdrawId = $this->withdrawRepo->createImmediate(
                    $dto->accountId,
                    $dto->method,
                    $dto->amount,
                    'INSUFFICIENT_FUNDS'
                );

                $pixEmail = null;
                if ($dto->method === 'pix' && $dto->pix) {
                    $pixData = [
                        'account_id' => $dto->accountId,
                        'key' => $dto->pix['key'],
                        'type' => $dto->pix['type'],
                        'provider' => $dto->pix['provider'] ?? 'fitbank',
                    ];
                    $this->pixRepo->store($withdrawId, $pixData);
                    $pixEmail = $dto->pix['key'] ?? null;
                }

                return [
                    'withdraw_id' => $withdrawId,
                    'status' => 'failed',
                    'error' => 'INSUFFICIENT_FUNDS',
                    'account_id' => $dto->accountId,
                    'amount' => $dto->amount,
                    'pix_email' => $pixEmail,
                ];
            }

            // saldo suficiente → debita e cria withdraw + PIX
            $withdrawId = $this->withdrawRepo->createImmediate(
                $dto->accountId,
                $dto->method,
                $dto->amount,
                null
            );

            $pixEmail = null;
            if ($dto->method === 'pix' && $dto->pix) {
                $pixData = [
                    'account_id' => $dto->accountId,
                    'key' => $dto->pix['key'],
                    'type' => $dto->pix['type'],
                    'provider' => $dto->pix['provider'] ?? 'fitbank',
                ];
                $this->pixRepo->store($withdrawId, $pixData);
                $pixEmail = $dto->pix['key'] ?? null;
            }

            return [
                'withdraw_id' => $withdrawId,
                'status' => 'done',
                'error' => null,
                'account_id' => $dto->accountId,
                'amount' => $dto->amount,
                'pix_email' => $pixEmail,
            ];
        });

        // 2) Pós-commit: e-mail imediato (apenas se a chave PIX for um e-mail)
        $this->notifyImmediate($result);

        // 3) Retorna exatamente no formato esperado
        if ($result['status'] === 'failed') {
            return [
                'withdraw_id' => $result['withdraw_id'],
                'status' => 'failed',
                'error' => $result['error'],
            ];
        }

        return [
            'withdraw_id' => $result['withdraw_id'],
            'status' => 'done',
        ];
    }

    /**
     * Envia e-mail imediatamente após o commit, se aplicável.
     */
    private function notifyImmediate(array $result): void
    {
        $container = ApplicationContext::getContainer();
        $log = $container->get(LoggerFactory::class)->get('withdraw');

        $to = $result['pix_email'] ?? null;
        if (! $to || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return; // nada para enviar
        }

        try {
            // Resolve mailer e view (ou usa construtor simples)
            $mailer = $container->has(SimpleSmtpMailer::class)
                ? $container->get(SimpleSmtpMailer::class)
                : new SimpleSmtpMailer();

            $status = $result['status'];
            $view = class_exists(View::class) ? View::class : null;

            if ($status === 'done') {
                $html = $view
                    ? $view::render('emails/withdraw_succeeded.php', [
                        'accountId'  => $result['account_id'],
                        'amount'     => number_format((float) $result['amount'], 2, ',', '.'),
                        'withdrawId' => $result['withdraw_id'],
                    ])
                    : $this->fallbackSuccessHtml($result);

                $mailer->sendHtml($to, 'Saque PIX realizado com sucesso', $html);
                $log->info('withdraw={withdraw} immediate_success_email_sent to={to}', [
                    'withdraw' => $result['withdraw_id'],
                    'to' => $to,
                ]);
            } elseif ($status === 'failed') {
                $html = $view
                    ? $view::render('emails/withdraw_failed.php', [
                        'accountId'  => $result['account_id'],
                        'amount'     => number_format((float) $result['amount'], 2, ',', '.'),
                        'withdrawId' => $result['withdraw_id'],
                        'errorCode'  => (string) ($result['error'] ?? 'UNKNOWN'),
                    ])
                    : $this->fallbackFailedHtml($result);

                $mailer->sendHtml($to, 'Falha no saque PIX', $html);
                $log->info('withdraw={withdraw} immediate_failed_email_sent to={to} code={code}', [
                    'withdraw' => $result['withdraw_id'],
                    'to' => $to,
                    'code' => (string) ($result['error'] ?? 'UNKNOWN'),
                ]);
            }
        } catch (\Throwable $e) {
            $log->error('withdraw={withdraw} immediate_mail_error {error}', [
                'withdraw' => $result['withdraw_id'] ?? 'n/a',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** HTML fallback simples caso não exista View::render */
    private function fallbackSuccessHtml(array $r): string
    {
        $amount = number_format((float) $r['amount'], 2, ',', '.');
        $acc = htmlspecialchars((string) $r['account_id'], ENT_QUOTES, 'UTF-8');
        $wid = htmlspecialchars((string) $r['withdraw_id'], ENT_QUOTES, 'UTF-8');
        return "<h1>Saque PIX concluído</h1><p>Conta: {$acc}</p><p>Valor: R$ {$amount}</p><p>ID do saque: {$wid}</p>";
    }

    private function fallbackFailedHtml(array $r): string
    {
        $amount = number_format((float) $r['amount'], 2, ',', '.');
        $acc = htmlspecialchars((string) $r['account_id'], ENT_QUOTES, 'UTF-8');
        $wid = htmlspecialchars((string) $r['withdraw_id'], ENT_QUOTES, 'UTF-8');
        $err = htmlspecialchars((string) ($r['error'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8');
        return "<h1>Falha no saque PIX</h1><p>Conta: {$acc}</p><p>Valor: R$ {$amount}</p><p>ID do saque: {$wid}</p><p>Motivo: {$err}</p>";
    }
}
