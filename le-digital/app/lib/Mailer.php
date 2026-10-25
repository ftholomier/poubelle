<?php
declare(strict_types=1);

namespace LD;

/**
 * Envoi d'e-mails : fonction mail() de PHP ou client SMTP natif (SSL/STARTTLS, AUTH LOGIN).
 */
final class Mailer
{
    /**
     * @param array $attachments [['name' => 'rdv.ics', 'type' => 'text/calendar', 'content' => '...']]
     */
    public static function send(string $to, string $subject, string $html, ?string $replyTo = null, array $attachments = []): bool
    {
        $mode = (string) Settings::get('mail.mode', 'mail');
        if ($mode === 'off' || $to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $fromEmail = (string) (Settings::get('mail.from_email') ?: ('no-reply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'le-digital.com')));
        $fromName = (string) Settings::get('mail.from_name', 'LE-DIGITAL.com');
        [$headers, $body] = self::build($fromEmail, $fromName, $to, $subject, $html, $replyTo, $attachments);
        try {
            $ok = $mode === 'smtp'
                ? self::smtp($fromEmail, $to, array_merge(['Subject: ' . self::encodeHeader($subject)], $headers), $body)
                : mail($to, self::encodeHeader($subject), $body, implode("\r\n", $headers), '-f' . $fromEmail);
        } catch (\Throwable $e) {
            Store::appendLog('mail', ['to' => $to, 'subject' => $subject, 'error' => $e->getMessage()]);
            return false;
        }
        Store::appendLog('mail', ['to' => $to, 'subject' => $subject, 'ok' => $ok]);
        return $ok;
    }

    private static function encodeHeader(string $s): string
    {
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function build(string $fromEmail, string $fromName, string $to, string $subject, string $html, ?string $replyTo, array $attachments): array
    {
        $boundary = 'ld_' . bin2hex(random_bytes(8));
        $alt = 'lda_' . bin2hex(random_bytes(8));
        $text = trim(html_entity_decode(strip_tags(preg_replace(['~<br\s*/?>~i', '~</(p|div|h\d|li|tr)>~i'], "\n", $html) ?? $html), ENT_QUOTES, 'UTF-8'));
        $headers = [
            'From: ' . self::encodeHeader($fromName) . ' <' . $fromEmail . '>',
            'MIME-Version: 1.0',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . (explode('@', $fromEmail)[1] ?? 'localhost') . '>',
            'X-Mailer: LE-DIGITAL.com',
        ];
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $altPart = "--$alt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . "--$alt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "--$alt--\r\n";
        if (!$attachments) {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $alt . '"';
            return [$headers, $altPart];
        }
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        $body = "--$boundary\r\nContent-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n" . $altPart;
        foreach ($attachments as $a) {
            $body .= "--$boundary\r\nContent-Type: " . $a['type'] . '; name="' . $a['name'] . "\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"" . $a['name'] . "\"\r\n\r\n"
                . chunk_split(base64_encode($a['content']));
        }
        $body .= "--$boundary--\r\n";
        return [$headers, $body];
    }

    private static function smtp(string $from, string $to, array $headers, string $body): bool
    {
        $host = (string) Settings::get('mail.smtp_host', '');
        $port = (int) Settings::get('mail.smtp_port', 587);
        $secure = (string) Settings::get('mail.smtp_secure', 'tls');
        $user = (string) Settings::get('mail.smtp_user', '');
        $pass = (string) (Secrets::get('smtp_password') ?? '');
        if ($host === '') {
            throw new \RuntimeException('Serveur SMTP non configuré.');
        }
        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new \RuntimeException("Connexion SMTP impossible : $errstr ($errno)");
        }
        stream_set_timeout($fp, 20);
        $read = static function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = static function (string $c, array $expect) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!in_array((int) substr($r, 0, 3), $expect, true)) {
                throw new \RuntimeException('SMTP : réponse inattendue à « ' . strtok($c, ' ') . ' » : ' . trim($r));
            }
            return $r;
        };
        $read();
        $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
        $cmd($ehlo, [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new \RuntimeException('STARTTLS impossible.');
            }
            $cmd($ehlo, [250]);
        }
        if ($user !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($user), [334]);
            $cmd(base64_encode($pass), [235]);
        }
        $cmd('MAIL FROM:<' . $from . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $msg = implode("\r\n", array_merge(['To: <' . $to . '>'], $headers)) . "\r\n\r\n" . $body;
        $msg = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $msg)) ?? $msg;
        fwrite($fp, $msg . "\r\n.\r\n");
        $r = $read();
        if ((int) substr($r, 0, 3) !== 250) {
            throw new \RuntimeException('SMTP : message refusé : ' . trim($r));
        }
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    }

    /** Gabarit HTML sobre aux couleurs du site. */
    public static function layout(string $title, string $content, ?string $ctaLabel = null, ?string $ctaUrl = null): string
    {
        $cta = $ctaLabel && $ctaUrl
            ? '<p style="margin:28px 0 0"><a href="' . e($ctaUrl) . '" style="display:inline-block;background:#cc1517;color:#fff;padding:14px 24px;border-radius:999px;font-weight:800;text-decoration:none;font-family:Montserrat,Arial,sans-serif">' . e($ctaLabel) . '</a></p>'
            : '';
        return '<!doctype html><html lang="fr"><body style="margin:0;background:#fff7f6;font-family:Arial,Helvetica,sans-serif;color:#161922">'
            . '<div style="max-width:600px;margin:0 auto;padding:32px 20px"><div style="background:#fff;border-radius:24px;padding:32px;border:1px solid #f2dedb">'
            . '<p style="margin:0 0 6px;font-weight:800;color:#cc1517;font-size:13px;letter-spacing:.1em;text-transform:uppercase">LE-DIGITAL.com</p>'
            . '<h1 style="margin:0 0 18px;font-size:24px;line-height:1.2">' . e($title) . '</h1>'
            . '<div style="font-size:15px;line-height:1.6;color:#3a3650">' . $content . '</div>' . $cta
            . '</div><p style="text-align:center;font-size:12px;color:#8a86a0;margin:18px 0 0">Le digital provoque la conversation. La conversation provoque la conversion.</p></div></body></html>';
    }
}
