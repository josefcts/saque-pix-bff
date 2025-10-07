<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use function Hyperf\Support\env;

final class SimpleSmtpMailer
{
    private string $host;
    private int $port;

    public function __construct(?string $host = null, ?int $port = null)
    {
        $this->host = $host ?: (env('MAIL_HOST') ?: 'mailhog');
        $this->port = $port ?: (int) (env('MAIL_PORT') ?: 1025);
    }

    public function sendHtml(string $to, string $subject, string $html, string $from = 'no-reply@local.test'): void
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
        if (! $socket) {
            throw new \RuntimeException("SMTP connect failed: $errno $errstr");
        }

        $this->expect($socket, 220);
        $this->write($socket, "EHLO localhost\r\n");
        $this->expect($socket, 250, true);

        $this->write($socket, "MAIL FROM:<{$from}>\r\n");
        $this->expect($socket, 250);

        $this->write($socket, "RCPT TO:<{$to}>\r\n");
        $this->expect($socket, 250);

        $this->write($socket, "DATA\r\n");
        $this->expect($socket, 354);

        $headers = [
            "From: {$from}",
            "To: {$to}",
            "Subject: " . $this->encode($subject),
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
        ];

        $data = implode("\r\n", $headers) . "\r\n\r\n" . $this->dotEscape($html) . "\r\n.\r\n";
        $this->write($socket, $data);
        $this->expect($socket, 250);

        $this->write($socket, "QUIT\r\n");
        fclose($socket);
    }

    private function write($socket, string $data): void
    {
        if (@fwrite($socket, $data) === false) {
            throw new \RuntimeException("SMTP write failed");
        }
    }

    private function expect($socket, int $code, bool $multi = false): void
    {
        $line = '';
        do {
            $line = fgets($socket, 2048);
            if ($line === false) {
                throw new \RuntimeException("SMTP read failed");
            }
        } while ($multi && str_starts_with($line, "{$code}-"));

        if ((int) substr($line, 0, 3) !== $code) {
            throw new \RuntimeException("SMTP expected $code, got: $line");
        }
    }

    private function encode(string $s): string
    {
        return '=?UTF-8?B?' . base64_encode($s) . '?=';
    }

    private function dotEscape(string $s): string
    {
        return preg_replace('/^\./m', '..', $s);
    }
}
