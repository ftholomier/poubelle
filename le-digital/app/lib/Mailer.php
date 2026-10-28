<?php
declare(strict_types=1);

namespace LD;

/**
 * Envoi d'e-mails : fonction mail() de PHP ou client SMTP natif (SSL/STARTTLS, AUTH LOGIN).
 */
final class Mailer
{
    /** Raison du dernier échec d'envoi (affichée dans le back-office). */
    public static ?string $lastError = null;

    /**
     * @param array $attachments [['name' => 'rdv.ics', 'type' => 'text/calendar', 'content' => '...']]
     */
    public static function send(string $to, string $subject, string $html, ?string $replyTo = null, array $attachments = []): bool
    {
        self::$lastError = null;
        $mode = (string) Settings::get('mail.mode', 'mail');
        if ($mode === 'off') {
            return self::fail($to, $subject, 'Envoi des e-mails désactivé (Paramètres › E-mails › Mode d’envoi).');
        }
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return self::fail($to, $subject, 'Adresse du destinataire absente ou invalide.');
        }
        $fromEmail = self::fromAddress();
        $fromName = (string) Settings::get('mail.from_name', 'LE-DIGITAL.com');
        [$headers, $body] = self::build($fromEmail, $fromName, $to, $subject, $html, $replyTo, $attachments);
        $via = $mode;
        try {
            if ($mode === 'smtp') {
                self::smtp($fromEmail, $to, array_merge(['Subject: ' . self::encodeHeader($subject)], $headers), $body);
            } else {
                $via = self::phpMail($to, self::encodeHeader($subject), $body, implode("\r\n", $headers), $fromEmail);
            }
        } catch (\Throwable $e) {
            return self::fail($to, $subject, $e->getMessage());
        }
        Store::appendLog('mail', ['to' => $to, 'subject' => $subject, 'ok' => true, 'via' => $via]);
        return true;
    }

    /**
     * Fonction mail() de PHP : avec l'adresse d'expédition imposée (-f), puis sans si l'hébergeur la refuse.
     * Retourne le mode réellement utilisé, ou lève une exception avec la raison.
     */
    private static function phpMail(string $to, string $subject, string $body, string $headers, string $from): string
    {
        if (!self::mailAvailable()) {
            throw new \RuntimeException('La fonction mail() de PHP est désactivée chez ton hébergeur : choisis l’envoi par SMTP.');
        }
        error_clear_last();
        if (@mail($to, $subject, $body, $headers, '-f' . $from)) {
            return 'mail';
        }
        $first = error_get_last()['message'] ?? '';
        error_clear_last();
        if (@mail($to, $subject, $body, $headers)) {
            return 'mail (sans -f)';
        }
        $err = error_get_last()['message'] ?? $first;
        throw new \RuntimeException('L’hébergeur a refusé l’envoi par mail()' . ($err !== '' ? ' (' . $err . ')' : '') . ' : choisis l’envoi par SMTP avec une vraie boîte e-mail.');
    }

    private static function fail(string $to, string $subject, string $error): bool
    {
        self::$lastError = $error;
        Store::appendLog('mail', ['to' => $to, 'subject' => $subject, 'ok' => false, 'error' => $error]);
        return false;
    }

    public static function mailAvailable(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return function_exists('mail') && !in_array('mail', $disabled, true);
    }

    /** Domaine du site (pour l'adresse d'expédition par défaut). */
    public static function domain(): string
    {
        $host = (string) (parse_url((string) Settings::get('site.url', ''), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? 'le-digital.com'));
        return (string) preg_replace(['/^www\./', '/:\d+$/'], '', strtolower($host));
    }

    public static function fromAddress(): string
    {
        $from = trim((string) Settings::get('mail.from_email', ''));
        if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }
        // En SMTP, la plupart des serveurs n'acceptent comme expéditeur que la boîte connectée.
        $user = trim((string) Settings::get('mail.smtp_user', ''));
        if (Settings::get('mail.mode') === 'smtp' && filter_var($user, FILTER_VALIDATE_EMAIL)) {
            return $user;
        }
        // Sinon l'e-mail public du site s'il est sur le même domaine (une vraie boîte, mieux acceptée que no-reply@).
        $contact = trim((string) Settings::get('contact.email', ''));
        if (filter_var($contact, FILTER_VALIDATE_EMAIL) && str_ends_with(strtolower($contact), '@' . self::domain())) {
            return $contact;
        }
        return 'no-reply@' . self::domain();
    }

    /** Adresse qui reçoit les demandes : celle des paramètres, sinon celle du compte administrateur. */
    public static function notifyAddress(): string
    {
        $to = trim((string) Settings::get('mail.notify_email', ''));
        if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $to;
        }
        foreach (Store::read('data/users.json', []) as $u) {
            if (filter_var($u['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                return (string) $u['email'];
            }
        }
        return '';
    }

    /** Derniers envois (journal), du plus récent au plus ancien. */
    public static function recent(int $n = 8): array
    {
        $file = Store::path('logs/mail.log');
        if (!is_file($file)) {
            return [];
        }
        $lines = array_slice(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -$n);
        return array_reverse(array_values(array_filter(array_map(static fn($l) => json_decode($l, true), $lines), 'is_array')));
    }

    /**
     * Vérifications affichées dans Paramètres › E-mails : [libellé, true|false|null, détail].
     */
    public static function diagnostics(): array
    {
        $mode = (string) Settings::get('mail.mode', 'mail');
        $to = self::notifyAddress();
        $from = self::fromAddress();
        $fromDomain = substr(strrchr($from, '@') ?: '@', 1);
        $checks = [];
        $checks[] = ['Adresse qui reçoit les demandes', $to !== '', $to !== '' ? $to . (trim((string) Settings::get('mail.notify_email', '')) === '' ? ' (celle de ton compte, aucune adresse saisie ci-dessous)' : '') : 'aucune : aucun e-mail ne peut partir'];
        $checks[] = ['Notification à chaque demande', (bool) Settings::get('mail.notify', true), Settings::get('mail.notify', true) ? 'activée' : 'désactivée (case ci-dessous)'];
        if ($mode === 'smtp') {
            $ready = Settings::get('mail.smtp_host') && (!Settings::get('mail.smtp_user') || Secrets::has('smtp_password'));
            $checks[] = ['Envoi par SMTP', (bool) $ready, $ready ? Settings::get('mail.smtp_host') . ':' . Settings::get('mail.smtp_port', 587) : 'serveur ou mot de passe SMTP manquant'];
        } elseif ($mode === 'mail') {
            $bin = strtok((string) (ini_get('sendmail_path') ?: '/usr/sbin/sendmail'), ' ');
            $binOk = !$bin || !str_starts_with($bin, '/') || @is_executable($bin);
            $checks[] = ['Fonction mail() de PHP', self::mailAvailable() && $binOk, !self::mailAvailable()
                ? 'désactivée par l’hébergeur : passe en SMTP'
                : ($binOk ? 'disponible (' . (ini_get('sendmail_path') ?: 'sendmail') . ')' : 'programme d’envoi introuvable sur le serveur (' . $bin . ') : passe en SMTP')];
        } else {
            $checks[] = ['Mode d’envoi', false, 'désactivé'];
        }
        $default = trim((string) Settings::get('mail.from_email', '')) === '' && str_starts_with($from, 'no-reply@');
        $checks[] = ['Adresse d’expédition', $default ? false : null, $from . ($default ? ' : adresse par défaut, souvent refusée par les hébergeurs si la boîte n’existe pas. Indique une vraie boîte de ton domaine (ex. contact@' . self::domain() . ').' : '')];
        if ($fromDomain !== '' && function_exists('dns_get_record')) {
            $txt = @dns_get_record($fromDomain, DNS_TXT) ?: [];
            $spf = '';
            foreach ($txt as $r) {
                $v = implode('', (array) ($r['entries'] ?? [$r['txt'] ?? '']));
                if (str_starts_with(strtolower($v), 'v=spf1')) {
                    $spf = $v;
                }
            }
            $checks[] = ['SPF du domaine ' . $fromDomain, null, $spf !== ''
                ? $spf . ' — le serveur qui envoie (ton hébergeur avec mail(), ou ton serveur SMTP) doit y être autorisé'
                : ($txt ? 'aucun enregistrement SPF : Gmail, Outlook… risquent de refuser tes e-mails' : 'vérification DNS impossible depuis le serveur')];
        }
        $last = self::recent(1)[0] ?? null;
        if ($last) {
            $checks[] = ['Dernier envoi', !empty($last['ok']), date('d/m/Y H:i', strtotime((string) $last['at'])) . ' à ' . ($last['to'] ?? '?') . ' : ' . (!empty($last['ok']) ? 'accepté par le serveur d’envoi' : ($last['error'] ?? 'échec'))];
        }
        return $checks;
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
            . '</div><p style="text-align:center;font-size:12px;color:#8a86a0;margin:18px 0 0">« Le digital provoque la conversation. La conversation provoque la conversion. »<br>— ' . e((string) Settings::get('site.owner', 'Frédéric Tholomier') ?: 'Frédéric Tholomier') . '</p></div></body></html>';
    }
}
