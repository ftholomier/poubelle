<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Core\Settings;

/**
 * Envoi d'e-mails : SMTP (STARTTLS ou SSL, authentification LOGIN) ou mail() de PHP.
 * Réglages : back-office > Réglages > E-mail. Les échecs sont journalisés
 * (storage/mail/AAAA-MM.jsonl) sans jamais bloquer la page.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $html, ?string $replyTo = null, array $attachments = []): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $fromEmail = (string) Settings::get('mail.from_email', '') ?: (string) Settings::get('general.contact_email', '');
        if ($fromEmail === '') {
            self::log($to, $subject, false, 'adresse d’expédition non réglée');
            return false;
        }
        $fromName = (string) Settings::get('mail.from_name', 'Sochaux Rétro');
        $boundary = 'sr' . bin2hex(random_bytes(12));
        $alt = 'alt' . bin2hex(random_bytes(8));
        $text = trim(html_entity_decode(strip_tags(preg_replace(['#<br\s*/?>#i', '#</p>#i', '#</h\d>#i', '#<li>#i'], ["\n", "\n\n", "\n\n", '• '], $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $headers = [
            'Date' => date('r'),
            'From' => self::addr($fromEmail, $fromName),
            'To' => $to,
            'Subject' => self::encode($subject),
            'Message-ID' => '<' . bin2hex(random_bytes(10)) . '@' . (parse_url(base_url(), PHP_URL_HOST) ?: 'sochauxretro.local') . '>',
            'MIME-Version' => '1.0',
        ];
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers['Reply-To'] = $replyTo;
        }
        $body = "--$alt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
            . "--$alt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode(self::layout($subject, $html))) . "--$alt--\r\n";
        if ($attachments) {
            $mixed = "--$boundary\r\nContent-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n" . $body;
            foreach ($attachments as $a) {
                $mixed .= "--$boundary\r\nContent-Type: " . ($a['type'] ?? 'application/octet-stream') . "; name=\"" . addslashes($a['name']) . "\"\r\nContent-Disposition: attachment; filename=\"" . addslashes($a['name']) . "\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($a['data']));
            }
            $mixed .= "--$boundary--\r\n";
            $headers['Content-Type'] = "multipart/mixed; boundary=\"$boundary\"";
            $body = $mixed;
        } else {
            $headers['Content-Type'] = "multipart/alternative; boundary=\"$alt\"";
        }
        $host = trim((string) Settings::get('mail.smtp_host', ''));
        try {
            $ok = $host !== '' ? self::smtp($host, $fromEmail, $to, $headers, $body) : self::phpMail($to, $headers, $body, $fromEmail);
            self::log($to, $subject, $ok, $ok ? '' : ($host !== '' ? 'le serveur SMTP a refusé l’envoi' : 'la fonction mail() du serveur a refusé l’envoi'));
            return $ok;
        } catch (\Throwable $e) {
            self::log($to, $subject, false, $e->getMessage());
            return false;
        }
    }

    /** Gabarit HTML aux couleurs du musée. */
    public static function layout(string $title, string $html): string
    {
        $site = e((string) Settings::get('general.site_name', 'Sochaux Rétro'));
        $base = base_url();
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . e($title) . '</title></head>'
            . '<body style="margin:0;background:#E8DFC9;font-family:Georgia,serif;color:#0E1F4D">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#E8DFC9;padding:24px 0"><tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFDF6;border:2px solid #0E1F4D">'
            . '<tr><td style="background:#1F3FA8;padding:18px 24px;color:#F3EDDF"><img src="' . e($base) . '/assets/img/logo-sochaux-retro.png" alt="" height="48" style="vertical-align:middle;margin-right:12px">'
            . '<span style="font-family:Arial Narrow,Arial,sans-serif;font-weight:bold;font-size:22px;text-transform:uppercase;letter-spacing:1px;vertical-align:middle">' . $site . '</span></td></tr>'
            . '<tr><td style="padding:24px;font-size:17px;line-height:1.55">' . $html . '</td></tr>'
            . '<tr><td style="padding:12px 24px;background:#E8DFC9;font-size:12px;color:#3A4A75;text-align:center">' . $site . ' · <a href="' . e($base) . '/" style="color:#1F3FA8">' . e(preg_replace('#^https?://#', '', $base)) . '</a></td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private static function addr(string $email, string $name): string
    {
        return $name !== '' ? self::encode($name) . " <$email>" : $email;
    }

    private static function encode(string $s): string
    {
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    /**
     * mail() de PHP. L'expéditeur d'enveloppe (-f) est l'adresse d'expédition : sans lui, l'hébergeur
     * met son propre compte système, et Gmail, Outlook… classent en indésirable ou refusent (SPF).
     */
    private static function phpMail(string $to, array $headers, string $body, string $from = ''): bool
    {
        $subject = $headers['Subject'];
        unset($headers['To'], $headers['Subject']);
        $h = '';
        foreach ($headers as $k => $v) {
            $h .= "$k: $v\r\n";
        }
        $env = filter_var($from, FILTER_VALIDATE_EMAIL) && preg_match('/^[A-Za-z0-9._%+@-]+$/', $from) ? '-f' . $from : '';
        return $env !== '' ? mail($to, $subject, $body, rtrim($h), $env) : mail($to, $subject, $body, rtrim($h));
    }

    private static function smtp(string $host, string $from, string $to, array $headers, string $body): bool
    {
        $port = (int) Settings::get('mail.smtp_port', 587);
        $secure = (string) Settings::get('mail.smtp_secure', 'tls');
        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new \RuntimeException("connexion SMTP impossible : $errstr");
        }
        stream_set_timeout($fp, 20);
        $read = function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = function (string $c, array $ok) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!in_array((int) substr($r, 0, 3), $ok, true)) {
                throw new \RuntimeException('SMTP : ' . trim($r));
            }
            return $r;
        };
        $read();
        $ehlo = parse_url(base_url(), PHP_URL_HOST) ?: 'localhost';
        $cmd("EHLO $ehlo", [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new \RuntimeException('STARTTLS impossible');
            }
            $cmd("EHLO $ehlo", [250]);
        }
        $user = (string) Settings::get('mail.smtp_user', '');
        if ($user !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($user), [334]);
            $cmd(base64_encode((string) Settings::get('mail.smtp_password', '')), [235]);
        }
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251]);
        $cmd('DATA', [354]);
        $h = '';
        foreach ($headers as $k => $v) {
            $h .= "$k: $v\r\n";
        }
        // Transparence SMTP : un point en début de ligne est doublé.
        $data = preg_replace('/^\./m', '..', $h . "\r\n" . $body);
        $cmd($data . "\r\n.", [250]);
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    }

    /** Derniers envois (mois en cours et précédent), les plus récents en premier. @return list<array> */
    public static function recent(int $limit = 15): array
    {
        $rows = JsonStore::readLines(STORAGE_PATH . '/mail/' . date('Y-m') . '.jsonl', $limit);
        if (count($rows) < $limit) {
            $rows = array_merge($rows, JsonStore::readLines(STORAGE_PATH . '/mail/' . date('Y-m', strtotime('first day of last month')) . '.jsonl', $limit - count($rows)));
        }
        return $rows;
    }

    /**
     * Mode d'envoi et points à vérifier : expéditeur réglé, domaine de l'expéditeur différent de
     * celui du site (avec mail(), le message part du serveur du site : un expéditeur @gmail.com ou
     * d'un autre domaine finit en indésirable ou est refusé). @return array{mode:string,from:string,warn:?string}
     */
    public static function diagnose(): array
    {
        $from = (string) Settings::get('mail.from_email', '') ?: (string) Settings::get('general.contact_email', '');
        $smtp = trim((string) Settings::get('mail.smtp_host', '')) !== '';
        $warn = null;
        if ($from !== '' && !$smtp) {
            $dom = strtolower((string) substr(strrchr($from, '@') ?: '', 1));
            $site = strtolower((string) parse_url(base_url(), PHP_URL_HOST));
            $root = implode('.', array_slice(explode('.', $site), -2));
            if ($dom !== '' && $dom !== $root && !str_ends_with($dom, '.' . $root)) {
                $warn = "L’adresse d’expédition ($dom) n’est pas sur le domaine du site ($root). Envoyés par mail() depuis le serveur du site, ces e-mails sont le plus souvent classés en indésirables ou refusés (Gmail, Outlook…). Utilisez une adresse @$root (créée dans le cPanel d’o2switch), ou renseignez le serveur SMTP de la boîte d’expédition.";
            }
        }
        return ['mode' => $smtp ? 'smtp' : 'mail', 'from' => $from, 'warn' => $warn];
    }

    private static function log(string $to, string $subject, bool $ok, string $error = ''): void
    {
        // L'adresse est masquée dans le journal (RGPD) : seul le domaine est conservé.
        $masked = preg_replace('/^[^@]*/', '***', $to);
        JsonStore::append(STORAGE_PATH . '/mail/' . date('Y-m') . '.jsonl', ['at' => date('c'), 'to' => $masked, 'subject' => mb_substr($subject, 0, 120), 'ok' => $ok, 'error' => $error]);
    }
}
