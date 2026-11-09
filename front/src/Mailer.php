<?php

declare(strict_types=1);

/**
 * Envoi des e-mails de notification : SMTP authentifié si réglé dans le back-office,
 * sinon la fonction mail() du serveur.
 */
final class Mailer
{
    /** @return true|string  true si envoyé, sinon le message d'erreur */
    public static function send(string $to, string $subject, string $text, string $replyTo = '')
    {
        $to = trim($to);
        if ($to === '') {
            return 'Aucun destinataire réglé.';
        }
        $from = (string) cfg('mail_from', 'no-reply@renovest.fr');
        $name = Content::site()['name'] ?? 'Site';
        $recipients = array_values(array_filter(array_map('trim', preg_split('/[,;]/', $to) ?: [])));
        foreach ($recipients as $r) {
            if (!filter_var($r, FILTER_VALIDATE_EMAIL)) {
                return 'Adresse invalide : ' . $r;
            }
        }
        if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $replyTo = '';
        }
        $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromHdr = '=?UTF-8?B?' . base64_encode($name) . '?= <' . $from . '>';
        $headers = [
            'From: ' . $fromHdr,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: Renovest-PHP',
        ];
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $body = str_replace(["\r\n", "\r"], "\n", $text);

        $host = trim((string) Settings::get('smtp_host', ''));
        if ($host !== '') {
            return self::smtp($host, $recipients, $from, $subjectEnc, $headers, $body);
        }
        $ok = @mail(implode(', ', $recipients), $subjectEnc, $body, implode("\r\n", $headers), '-f' . $from);
        return $ok ? true : 'La fonction mail() du serveur a refusé l\'envoi.';
    }

    /** Client SMTP minimal (STARTTLS ou SSL, AUTH LOGIN). */
    private static function smtp(string $host, array $to, string $from, string $subject, array $headers, string $body)
    {
        $port = (int) Settings::get('smtp_port', 587);
        $secure = (string) Settings::get('smtp_secure', 'tls');
        $user = (string) Settings::get('smtp_user', '');
        $pass = Settings::secret('smtp_pass');
        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            return 'Connexion SMTP impossible : ' . $errstr;
        }
        stream_set_timeout($fp, 20);
        $read = function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = function (string $c, array $ok) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!in_array((int) substr($r, 0, 3), $ok, true)) {
                throw new RuntimeException('SMTP : ' . trim($r));
            }
            return $r;
        };
        try {
            $read();
            $ehlo = parse_url((string) cfg('site_url'), PHP_URL_HOST) ?: 'localhost';
            $cmd('EHLO ' . $ehlo, [250]);
            if ($secure === 'tls') {
                $cmd('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP : échec du chiffrement TLS');
                }
                $cmd('EHLO ' . $ehlo, [250]);
            }
            if ($user !== '') {
                $cmd('AUTH LOGIN', [334]);
                $cmd(base64_encode($user), [334]);
                $cmd(base64_encode($pass), [235]);
            }
            $cmd('MAIL FROM:<' . $from . '>', [250]);
            foreach ($to as $r) {
                $cmd('RCPT TO:<' . $r . '>', [250, 251]);
            }
            $cmd('DATA', [354]);
            $msg = 'To: ' . implode(', ', $to) . "\r\nSubject: " . $subject . "\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . '@' . $ehlo . ">\r\n"
                . implode("\r\n", $headers) . "\r\n\r\n"
                . preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $body));
            $cmd($msg . "\r\n.", [250]);
            $cmd('QUIT', [221]);
        } catch (Throwable $e) {
            @fclose($fp);
            return $e->getMessage();
        }
        fclose($fp);
        return true;
    }
}
