<?php

declare(strict_types=1);

return [
    // Garante que o scheduler do Hyperf de fato roda em um process separado
    Hyperf\Crontab\Process\CrontabDispatcherProcess::class,
    Hyperf\AsyncQueue\Process\ConsumerProcess::class
];
