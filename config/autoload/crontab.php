<?php

declare(strict_types=1);

use Hyperf\Crontab\Crontab;
use App\Application\Command\ProcessScheduledWithdrawalsCommand;

return [
    'enable' => true,
    'crontab' => [
        (new Crontab())
            ->setName('scan-scheduled-withdrawals')
            ->setRule('*/1 * * * *') // a cada minuto
            ->setCallback([ProcessScheduledWithdrawalsCommand::class, 'handle'])
            ->setMemo('Scan & enqueue scheduled withdrawals')
            ->setEnable(true)
            ->setSingleton(true)     // evita overlap no mesmo nó
            ->setOnOneServer(false), // se usar vários nós + Redis Locker -> true
    ],
];
