<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Envoi d'emails natif : SMTP (SSL/STARTTLS, AUTH LOGIN/PLAIN), mail() de PHP ou journal (développement).
 * Messages multipart texte + HTML encodés en quoted-printable.
 */
final class Mailer
{
    /** @var resource|null */
    private static $conn = null;
    private static bool $keepAlive = false;

    /**
     * @param array{to:string|array, subject:string, html?:string, text?:string, reply_to?:string, from?:string, from_name?:string, unsubscribe?:string, headers?:array} $m
     * @return array{ok:bool, error:?string, id:string}
     */
    public static function send(array $m): array
    {
        $driver = strtolower((string) Env::get('MAIL_DRIVER', 'log'));
        $from = (string) ($m['from'] ?? Env::get('MAIL_FROM_ADDRESS', Env::get('CONTACT_EMAIL', 'no-reply@localhost')));
        $fromName = (string) ($m['from_name'] ?? Env::get('MAIL_FROM_NAME', Env::get('APP_NAME', 'Animateur Pour Votre Soirée')));
        $to = self::normalizeRecipients($m['to']);
        if (!$to) {
            return ['ok' => false, 'error' => 'Destinataire invalide', 'id' => ''];
        }
        $domain = Str::domain((string) Env::get('APP_URL', 'http://localhost')) ?: 'localhost';
        $id = bin2hex(random_bytes(12)) . '@' . $domain;
        $html = (string) ($m['html'] ?? '');
        $text = (string) ($m['text'] ?? '');
        if ($text === '' && $html !== '') {
            $text = self::htmlToText($html);
        }
        $boundary = '=_apvs_' . bin2hex(random_bytes(10));
        $headers = [
            'Date' => date('r'),
            'From' => self::address($from, $fromName),
            'To' => implode(', ', array_map(static fn ($e, $n) => self::address($e, $n), array_keys($to), $to)),
            'Subject' => self::encodeHeader((string) $m['subject']),
            'Message-ID' => '<' . $id . '>',
            'MIME-Version' => '1.0',
        ];
        $reply = (string) ($m['reply_to'] ?? Env::get('MAIL_REPLY_TO', ''));
        if ($reply !== '' && Str::emailValid($reply)) {
            $headers['Reply-To'] = self::address($reply, (string) ($m['reply_to_name'] ?? ''));
        }
        if (!empty($m['unsubscribe'])) {
            $headers['List-Unsubscribe'] = '<' . $m['unsubscribe'] . '>';
            $headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }
        foreach (($m['headers'] ?? []) as $k => $v) {
            $headers[(string) $k] = (string) $v;
        }
        if ($html !== '') {
            $headers['Content-Type'] = 'multipart/alternative; boundary="' . $boundary . '"';
            $body = "This is a multi-part message in MIME format.\r\n\r\n"
                . '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
                . self::qp($text) . "\r\n"
                . '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
                . self::qp($html) . "\r\n"
                . '--' . $boundary . "--\r\n";
        } else {
            $headers['Content-Type'] = 'text/plain; charset=UTF-8';
            $headers['Content-Transfer-Encoding'] = 'quoted-printable';
            $body = self::qp($text) . "\r\n";
        }
        $headerStr = '';
        foreach ($headers as $k => $v) {
            $headerStr .= $k . ': ' . str_replace(["\r", "\n"], '', $v) . "\r\n";
        }

        try {
            switch ($driver) {
                case 'smtp':
                    self::smtpSend($from, array_keys($to), $headerStr . "\r\n" . $body);
                    break;
                case 'mail':
                    $h = $headers;
                    unset($h['To'], $h['Subject']);
                    $hs = '';
                    foreach ($h as $k => $v) {
                        $hs .= $k . ': ' . str_replace(["\r", "\n"], '', $v) . "\r\n";
                    }
                    // certains hébergements refusent l'option -f (adresse de retour) : nouvel essai sans elle
                    if (!@mail(implode(', ', array_keys($to)), $headers['Subject'], $body, rtrim($hs), '-f' . $from)
                        && !@mail(implode(', ', array_keys($to)), $headers['Subject'], $body, rtrim($hs))) {
                        throw new \RuntimeException('mail() a échoué');
                    }
                    break;
                default:
                    $dir = STORAGE_PATH . '/logs/mail';
                    Fs::ensureDir($dir);
                    file_put_contents($dir . '/' . date('Ymd-His') . '-' . substr($id, 0, 8) . '.eml', $headerStr . "\r\n" . $body);
            }
            Logger::log('mail', 'Email envoyé', ['to' => array_keys($to), 'subject' => $m['subject'], 'driver' => $driver]);
            return ['ok' => true, 'error' => null, 'id' => $id];
        } catch (\Throwable $e) {
            self::disconnect();
            Logger::log('mail', 'Échec envoi email', ['to' => array_keys($to), 'subject' => $m['subject'], 'error' => $e->getMessage()], 'error');
            return ['ok' => false, 'error' => $e->getMessage(), 'id' => $id];
        }
    }

    /** Garde la connexion SMTP ouverte pendant un envoi en lot. */
    public static function batch(callable $fn): void
    {
        self::$keepAlive = true;
        try {
            $fn();
        } finally {
            self::$keepAlive = false;
            self::disconnect();
        }
    }

    private static function normalizeRecipients(string|array $to): array
    {
        $out = [];
        $list = is_array($to) ? $to : [$to => ''];
        foreach ($list as $k => $v) {
            [$email, $name] = is_int($k) ? [(string) $v, ''] : [(string) $k, (string) $v];
            $email = trim(str_replace(["\r", "\n"], '', $email));
            if (Str::emailValid($email)) {
                $out[$email] = $name;
            }
        }
        return $out;
    }

    private static function address(string $email, string $name = ''): string
    {
        $name = trim(str_replace(["\r", "\n", '"'], '', $name));
        if ($name === '') {
            return '<' . $email . '>';
        }
        return (preg_match('/[^\x20-\x7E]/', $name) ? self::encodeHeader($name) : '"' . $name . '"') . ' <' . $email . '>';
    }

    public static function encodeHeader(string $s): string
    {
        $s = str_replace(["\r", "\n"], ' ', $s);
        if (!preg_match('/[^\x20-\x7E]/', $s)) {
            return $s;
        }
        return mb_encode_mimeheader($s, 'UTF-8', 'B', "\r\n");
    }

    private static function qp(string $s): string
    {
        // quoted_printable_encode() attend des fins de ligne CRLF (un \n seul serait encodé en =0A).
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        return quoted_printable_encode(str_replace("\n", "\r\n", $s));
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(style|script|head)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace_callback('#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', static fn ($m) => trim(strip_tags($m[2])) . ' (' . $m[1] . ')', $html) ?? $html;
        $html = preg_replace('#<(br|/p|/div|/tr|/h[1-6]|/li)\b[^>]*>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<li\b[^>]*>#i', '- ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    // ------------------------------------------------------------------- SMTP

    private static function smtpSend(string $from, array $rcpts, string $data): void
    {
        $fp = self::connect();
        self::cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        foreach ($rcpts as $r) {
            self::cmd($fp, 'RCPT TO:<' . $r . '>', [250, 251]);
        }
        self::cmd($fp, 'DATA', [354]);
        $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $data)) ?? $data;
        $data = str_replace("\n", "\r\n", $data);
        fwrite($fp, $data . "\r\n.\r\n");
        self::expect($fp, [250]);
        if (self::$keepAlive) {
            self::cmd($fp, 'RSET', [250]);
        } else {
            self::disconnect();
        }
    }

    /** @return resource */
    private static function connect()
    {
        if (self::$conn !== null && is_resource(self::$conn)) {
            return self::$conn;
        }
        $host = (string) Env::get('MAIL_HOST', 'localhost');
        $port = Env::int('MAIL_PORT', 587);
        $enc = strtolower((string) Env::get('MAIL_ENCRYPTION', 'tls'));
        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new \RuntimeException('Connexion SMTP impossible (' . $host . ':' . $port . ') : ' . $errstr);
        }
        stream_set_timeout($fp, 30);
        self::expect($fp, [220]);
        $ehloHost = Str::domain((string) Env::get('APP_URL', '')) ?: 'localhost';
        $caps = self::cmd($fp, 'EHLO ' . $ehloHost, [250]);
        if ($enc === 'tls') {
            self::cmd($fp, 'STARTTLS', [220]);
            $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
            }
            if (!@stream_socket_enable_crypto($fp, true, $method)) {
                throw new \RuntimeException('Négociation TLS impossible avec le serveur SMTP');
            }
            $caps = self::cmd($fp, 'EHLO ' . $ehloHost, [250]);
        }
        $user = (string) Env::get('MAIL_USERNAME', '');
        $pass = (string) Env::get('MAIL_PASSWORD', '');
        if ($user !== '') {
            if (stripos($caps, 'PLAIN') !== false) {
                self::cmd($fp, 'AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), [235], true);
            } else {
                self::cmd($fp, 'AUTH LOGIN', [334], true);
                self::cmd($fp, base64_encode($user), [334], true);
                self::cmd($fp, base64_encode($pass), [235], true);
            }
        }
        return self::$conn = $fp;
    }

    private static function disconnect(): void
    {
        if (self::$conn !== null && is_resource(self::$conn)) {
            @fwrite(self::$conn, "QUIT\r\n");
            @fclose(self::$conn);
        }
        self::$conn = null;
    }

    /** @param resource $fp */
    private static function cmd($fp, string $cmd, array $codes, bool $sensitive = false): string
    {
        fwrite($fp, $cmd . "\r\n");
        return self::expect($fp, $codes, $sensitive ? 'AUTH' : $cmd);
    }

    /** @param resource $fp */
    private static function expect($fp, array $codes, string $context = ''): string
    {
        $resp = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $resp .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($resp, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException('SMTP ' . ($context !== '' ? '[' . $context . '] ' : '') . trim($resp ?: 'pas de réponse'));
        }
        return $resp;
    }
}
