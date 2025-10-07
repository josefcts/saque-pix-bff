<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Job\ProcessWithdrawJob;
use App\Domain\Withdraw\Model\AccountWithdraw;
use Carbon\Carbon;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\DbConnection\Db;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;

final class ProcessScheduledWithdrawalsCommand
{
    private const BATCH = 100;

    public function __construct(
        private DriverFactory $driverFactory,
        private ContainerInterface $container,
    ) {
    }

    public function handle(): void
    {
        $logger = $this->container->get(LoggerFactory::class)->get('cron');

        $nowUtc = Carbon::now('UTC');
        $queued = 0;

        Db::transaction(function () use ($nowUtc, &$queued) {
            $rows = AccountWithdraw::query()
                ->where('done', false)
                ->where('error', false)
                ->whereNull('queued_at')
                ->whereNotNull('scheduled_for')
                ->where('scheduled_for', '<=', $nowUtc)
                ->orderBy('scheduled_for')
                ->limit(self::BATCH)
                ->lockForUpdate()
                ->get(['id']);

            if ($rows->isEmpty()) {
                return;
            }

            $driver = $this->driverFactory->get('default');

            foreach ($rows as $row) {
                $affected = AccountWithdraw::query()
                    ->where('id', $row->id)
                    ->whereNull('queued_at')
                    ->update(['queued_at' => $nowUtc]);

                if ($affected === 0) {
                    continue;
                }

                $driver->push(new ProcessWithdrawJob($row->id), 0);
                $queued++;
            }
        });

        $logger->info(sprintf('[cron] scheduled withdrawals enqueued=%d now=%s', $queued, $nowUtc->toIso8601String()));
    }
}
