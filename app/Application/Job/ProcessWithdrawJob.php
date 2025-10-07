<?php

declare(strict_types=1);

namespace App\Application\Job;

use App\Domain\Withdraw\Model\AccountWithdraw;
use App\Domain\Withdraw\Model\AccountWithdrawPix;
use App\Domain\Account\Model\Account;
use App\Infrastructure\Mail\SimpleSmtpMailer;
use App\Infrastructure\Mail\View;
use Hyperf\AsyncQueue\Job;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use Hyperf\Logger\LoggerFactory;
use Carbon\Carbon;

final class ProcessWithdrawJob extends Job
{
    public function __construct(private string $withdrawId)
    {
    }

    public function handle(): void
    {
        $container = ApplicationContext::getContainer();
        $log = $container->get(LoggerFactory::class)->get('withdraw');

        // Vamos decidir a notificação após a transação
        $postCommitAction = [
            'type' => 'none',            // none | success | failed
            'error_code' => null,        // string|null
            'withdraw_id' => $this->withdrawId,
        ];

        try {
            Db::transaction(function () use (&$postCommitAction, $log) {
                /** @var AccountWithdraw|null $w */
                $w = AccountWithdraw::query()
                    ->lockForUpdate()
                    ->find($this->withdrawId);

                if (!$w) {
                    $log->warning('withdraw={withdraw} not_found', [
                        'withdraw' => $this->withdrawId,
                    ]);
                    // nada para fazer pós-commit
                    return;
                }

                if ($w->done) {
                    $log->info('withdraw={withdraw} already_done skip', [
                        'withdraw' => $this->withdrawId,
                    ]);
                    // nada para fazer pós-commit
                    return;
                }

                $accountId = (string) $w->account_id;
                $amount = (float) $w->amount;

                $acc = Account::query()->find($accountId);
                if (!$acc) {
                    $w->fill([
                        'done' => true,
                        'error' => true,
                        'error_code' => 'ACCOUNT_NOT_FOUND',
                        'updated_at' => Carbon::now('UTC'),
                    ])->save();

                    $log->warning('withdraw={withdraw} account={account} not_found', [
                        'withdraw' => $this->withdrawId,
                        'account' => $accountId,
                    ]);

                    $postCommitAction['type'] = 'failed';
                    $postCommitAction['error_code'] = 'ACCOUNT_NOT_FOUND';
                    return;
                }

                $affected = Account::query()
                    ->whereKey($accountId)
                    ->where('balance', '>=', $amount)
                    ->decrement('balance', $amount);

                $log->info('withdraw={withdraw} account={account} try_debit amount={amount} affected={affected}', [
                    'withdraw' => $this->withdrawId,
                    'account' => $accountId,
                    'amount' => $amount,
                    'affected' => $affected,
                ]);

                if ($affected === 0) {
                    $w->fill([
                        'done' => true,
                        'error' => true,
                        'error_code' => 'INSUFFICIENT_FUNDS',
                        'updated_at' => Carbon::now('UTC'),
                    ])->save();

                    $log->warning('withdraw={withdraw} account={account} debit_failed INSUFFICIENT_FUNDS', [
                        'withdraw' => $this->withdrawId,
                        'account' => $accountId,
                    ]);

                    $postCommitAction['type'] = 'failed';
                    $postCommitAction['error_code'] = 'INSUFFICIENT_FUNDS';
                    return;
                }

                // Atualiza o updated_at da conta para refletir a movimentação
                Account::query()->whereKey($accountId)->update(['updated_at' => Carbon::now('UTC')]);

                // Conclui o saque
                $w->fill([
                    'done' => true,
                    'error' => false,
                    'error_code' => null,
                    'updated_at' => Carbon::now('UTC'),
                ])->save();

                $log->info('withdraw={withdraw} account={account} processed_ok', [
                    'withdraw' => $this->withdrawId,
                    'account' => $accountId,
                ]);

                $postCommitAction['type'] = 'success';
            });

            // Fora da transação: decide e envia notificação (evita enviar antes de commit)
            if ($postCommitAction['type'] === 'success') {
                $this->notifySuccess($this->withdrawId, $log);
            } elseif ($postCommitAction['type'] === 'failed') {
                $this->notifyFailed($this->withdrawId, (string) $postCommitAction['error_code'], $log);
            }
        } catch (\Throwable $e) {
            // Falha inesperada na transação/execução do job
            $log->error('withdraw={withdraw} unexpected_error {error}', [
                'withdraw' => $this->withdrawId,
                'error' => $e->getMessage(),
            ]);
            // Não tenta enviar e-mail aqui; estado pode ser incerto.
        }
    }

    /**
     * Envia e-mail de sucesso com base no estado persistido do saque.
     * Busca novamente os dados já COMMITADOS.
     */
    private function notifySuccess(string $withdrawId, $log): void
    {
        try {
            /** @var AccountWithdraw|null $w */
            $w = AccountWithdraw::query()->find($withdrawId);
            if (!$w || $w->error || !$w->done) {
                // Estado não condiz com sucesso; não envia
                return;
            }

            $pix = AccountWithdrawPix::query()
                ->where('account_withdraw_id', $w->id)
                ->first();

            $to = $pix?->key;
            if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            $container = ApplicationContext::getContainer();

            /** @var SimpleSmtpMailer $mailer */
            $mailer = $container->has(SimpleSmtpMailer::class)
                ? $container->get(SimpleSmtpMailer::class)
                : new SimpleSmtpMailer();

            /** @var View $view */
            $view = $container->has(View::class)
                ? $container->get(View::class)
                : new View();

            $html = $view::render('emails/withdraw_succeeded.php', [
                'accountId'  => $w->account_id,
                'amount'     => number_format((float) $w->amount, 2, ',', '.'),
                'withdrawId' => $w->id,
            ]);

            $mailer->sendHtml($to, 'Saque PIX realizado com sucesso', $html);

            $log->info('withdraw={withdraw} success_email_sent to={to}', [
                'withdraw' => $w->id,
                'to' => $to,
            ]);
        } catch (\Throwable $e) {
            $log->error('withdraw={withdraw} mail_error {error}', [
                'withdraw' => $withdrawId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envia e-mail de falha com base no estado persistido do saque.
     * Busca novamente os dados já COMMITADOS.
     */
    private function notifyFailed(string $withdrawId, string $errorCode, $log): void
    {
        try {
            /** @var AccountWithdraw|null $w */
            $w = AccountWithdraw::query()->find($withdrawId);
            if (!$w || !$w->error || !$w->done) {
                // Estado não condiz com falha; não envia
                return;
            }

            $pix = AccountWithdrawPix::query()
                ->where('account_withdraw_id', $w->id)
                ->first();

            $to = $pix?->key;
            if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            $container = ApplicationContext::getContainer();

            /** @var SimpleSmtpMailer $mailer */
            $mailer = $container->has(SimpleSmtpMailer::class)
                ? $container->get(SimpleSmtpMailer::class)
                : new SimpleSmtpMailer();

            /** @var View $view */
            $view = $container->has(View::class)
                ? $container->get(View::class)
                : new View();

            $html = $view::render('emails/withdraw_failed.php', [
                'accountId'  => $w->account_id,
                'amount'     => number_format((float) $w->amount, 2, ',', '.'),
                'withdrawId' => $w->id,
                'errorCode'  => $errorCode,
            ]);

            $mailer->sendHtml($to, 'Falha no saque PIX', $html);

            $log->info('withdraw={withdraw} failed_email_sent to={to} code={code}', [
                'withdraw' => $w->id,
                'to' => $to,
                'code' => $errorCode,
            ]);
        } catch (\Throwable $e) {
            $log->error('withdraw={withdraw} mail_error {error}', [
                'withdraw' => $withdrawId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
