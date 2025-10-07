<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

final class View
{
    public static function render(string $path, array $data = []): string
    {
        $full = BASE_PATH . '/storage/views/' . ltrim($path, '/');
        if (! is_file($full)) {
            throw new \RuntimeException("View not found: {$full}");
        }
        extract($data, EXTR_OVERWRITE);
        ob_start();
        include $full;
        return (string) ob_get_clean();
    }
}
