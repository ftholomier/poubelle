<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Config;
use App\Core\Logger;

/**
 * Envoi d'e-mails en PHP natif : fonction mail() ou SMTP direct (sockets).
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody, ?string $replyTo = null): bool
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        // Anti-injection d'en-têtes.
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        $replyTo = $replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : null;

        $from     = Config::str('mail.from', 'no-reply@localhost');
        $fromName = Config::str('mail.from_name', 'Site');
        $boundary = 'lcal' . bin2hex(random_bytes(8));
        $text     = trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>#i', "\n", $htmlBody) ?? ''), ENT_QUOTES, 'UTF-8'));

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . self::encodeName($fromName) . ' <' . $from . '>',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: LeComptableALunettes',
        ];
        if ($replyTo !== null) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n{$text}\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n{$htmlBody}\r\n\r\n"
            . "--{$boundary}--";

        $transport = Config::str('mail.transport', 'mail');

        if ($transport === 'log') {
            Logger::info('E-mail (mode journal)', ['to' => $to, 'subject' => $subject]);
            @file_put_contents(
                STORAGE_PATH . '/logs/mail.log',
                "=== " . date('c') . " → {$to}\n{$subject}\n{$text}\n\n",
                FILE_APPEND
            );
            return true;
        }

        if ($transport === 'smtp') {
            return self::smtp($to, $subject, $body, $headers);
        }

        $ok = @mail(
            $to,
            '=?UTF-8?B?' . base64_encode($subject) . '?=',
            $body,
            implode("\r\n", $headers)
        );
        if (!$ok) {
            Logger::error('Envoi e-mail échoué', ['to' => $to]);
        }
        return $ok;
    }

    private static function encodeName(string $name): string
    {
        return '=?UTF-8?B?' . base64_encode($name) . '?=';
    }

    /** @param array<int,string> $headers */
    private static function smtp(string $to, string $subject, string $body, array $headers): bool
    {
        $host = Config::str('mail.smtp.host', '');
        $port = Config::int('mail.smtp.port', 587);
        $user = Config::str('mail.smtp.user', '');
        $pass = Config::str('mail.smtp.pass', '');
        $enc  = Config::str('mail.smtp.encryption', 'tls');
        if ($host === '') {
            return false;
        }

        $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, 15);
        if (!$socket) {
            Logger::error('Connexion SMTP impossible', ['host' => $host, 'error' => $errstr]);
            return false;
        }
        stream_set_timeout($socket, 15);

        $read = static function () use ($socket): string {
            $data = '';
            while (($line = fgets($socket, 515)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $write = static function (string $command) use ($socket, $read): string {
            fwrite($socket, $command . "\r\n");
            return $read();
        };

        $read();
        $domain = parse_url(Config::str('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';
        $write('EHLO ' . $domain);

        if ($enc === 'tls') {
            $write('STARTTLS');
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                Logger::error('STARTTLS refusé');
                return false;
            }
            $write('EHLO ' . $domain);
        }

        if ($user !== '') {
            $write('AUTH LOGIN');
            $write(base64_encode($user));
            $response = $write(base64_encode($pass));
            if (!str_starts_with(trim($response), '235')) {
                fclose($socket);
                Logger::error('Authentification SMTP refusée');
                return false;
            }
        }

        $from = Config::str('mail.from', 'no-reply@localhost');
        $write('MAIL FROM:<' . $from . '>');
        $write('RCPT TO:<' . $to . '>');
        $response = $write('DATA');
        if (!str_starts_with(trim($response), '354')) {
            fclose($socket);
            return false;
        }

        $message = implode("\r\n", array_merge($headers, [
            'To: ' . $to,
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Date: ' . date('r'),
        ])) . "\r\n\r\n" . $body;
        // Protection du point isolé en début de ligne.
        $message = preg_replace('/^\./m', '..', $message) ?? $message;

        $response = $write($message . "\r\n.");
        $write('QUIT');
        fclose($socket);

        $ok = str_starts_with(trim($response), '250');
        if (!$ok) {
            Logger::error('SMTP a refusé le message', ['response' => substr($response, 0, 120)]);
        }
        return $ok;
    }
}
