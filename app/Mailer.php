<?php
declare(strict_types=1);

namespace App;

/**
 * Envoi d'emails : mail() par défaut, SMTP si les réglages sont renseignés
 * (back-office ou .env). Gabarits texte + HTML à la charte iOiO.
 */
final class Mailer
{
    public static function fromAddress(): string
    {
        $configured = Config::get('MAIL_FROM');
        if ($configured) {
            return $configured;
        }
        $host = preg_replace('/^www\./', '', (string) parse_url(Config::baseUrl(), PHP_URL_HOST)) ?: 'localhost';
        return 'no-reply@' . $host;
    }

    public static function fromName(): string
    {
        return Config::get('MAIL_FROM_NAME') ?? (string) (Content::settings()['site']['name'] ?? 'Le iOiO');
    }

    /** Boîte qui reçoit les demandes du site. */
    public static function inbox(): string
    {
        $settings = Content::settings();
        return (string) (Config::get('MAIL_TO') ?? $settings['contact']['email'] ?? self::fromAddress());
    }

    public static function send(string $to, string $subject, string $html, string $text = '', string $replyTo = ''): bool
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::write('mail', 'Destinataire invalide : ' . $to);
            return false;
        }
        $text = $text !== '' ? $text : trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
        $body = self::wrap($subject, $html);

        $ok = Config::has('SMTP_HOST')
            ? self::sendSmtp($to, $subject, $body, $text, $replyTo)
            : self::sendMail($to, $subject, $body, $text, $replyTo);

        Log::write('mail', ($ok ? 'Envoyé' : 'Échec') . ' → ' . $to . ' : ' . $subject);
        return $ok;
    }

    public static function sendPasswordReset(string $to, string $link): bool
    {
        $html = '<p>Vous avez demandé la réinitialisation de votre mot de passe du back-office du iOiO.</p>'
            . '<p><a class="btn" href="' . Text::e($link) . '">Choisir un nouveau mot de passe</a></p>'
            . '<p class="muted">Ce lien est valable 30 minutes et ne fonctionne qu\'une seule fois. '
            . 'Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet email : rien ne change.</p>'
            . '<p class="muted">' . Text::e($link) . '</p>';
        return self::send($to, 'Réinitialisation de votre mot de passe — Le iOiO', $html);
    }

    // ------------------------------------------------------------- transports

    private static function sendMail(string $to, string $subject, string $html, string $text, string $replyTo): bool
    {
        $boundary = 'ioio' . bin2hex(random_bytes(8));
        $headers = self::headers($replyTo, $boundary);
        $body = self::multipart($boundary, $text, $html);
        $subject = self::encodeHeader($subject);
        $extra = '-f' . self::fromAddress();
        return @mail($to, $subject, $body, implode("\r\n", $headers), $extra);
    }

    private static function sendSmtp(string $to, string $subject, string $html, string $text, string $replyTo): bool
    {
        $host = (string) Config::get('SMTP_HOST');
        $port = (int) (Config::get('SMTP_PORT') ?? 587);
        $secure = strtolower((string) (Config::get('SMTP_SECURE') ?? 'tls')); // tls | ssl | none
        $user = (string) Config::get('SMTP_USER', '');
        $pass = (string) Config::get('SMTP_PASS', '');
        $timeout = 12;

        $endpoint = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($endpoint, $errno, $errstr, $timeout);
        if ($socket === false) {
            Log::write('mail', 'SMTP injoignable : ' . $errstr);
            return false;
        }
        stream_set_timeout($socket, $timeout);

        $read = static function () use ($socket): string {
            $out = '';
            while (($line = fgets($socket, 515)) !== false) {
                $out .= $line;
                if (\strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }
            return $out;
        };
        $cmd = static function (string $command, string $expect) use ($socket, $read): bool {
            if ($command !== '') {
                fwrite($socket, $command . "\r\n");
            }
            $response = $read();
            if (!str_starts_with($response, $expect)) {
                Log::write('mail', 'SMTP « ' . substr($command, 0, 20) . ' » → ' . trim($response));
                return false;
            }
            return true;
        };

        $ehlo = 'EHLO ' . (parse_url(Config::baseUrl(), PHP_URL_HOST) ?: 'localhost');
        $ok = $cmd('', '220') && $cmd($ehlo, '250');

        if ($ok && $secure === 'tls') {
            $ok = $cmd('STARTTLS', '220')
                && @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
                && $cmd($ehlo, '250');
        }
        if ($ok && $user !== '') {
            $ok = $cmd('AUTH LOGIN', '334')
                && $cmd(base64_encode($user), '334')
                && $cmd(base64_encode($pass), '235');
        }

        $boundary = 'ioio' . bin2hex(random_bytes(8));
        $headers = array_merge(self::headers($replyTo, $boundary), [
            'To: ' . $to,
            'Subject: ' . self::encodeHeader($subject),
            'Date: ' . date(\DATE_RFC2822),
        ]);
        $data = implode("\r\n", $headers) . "\r\n\r\n" . self::multipart($boundary, $text, $html);
        $data = preg_replace('/^\./m', '..', $data) ?? $data;

        $ok = $ok
            && $cmd('MAIL FROM:<' . self::fromAddress() . '>', '250')
            && $cmd('RCPT TO:<' . $to . '>', '250')
            && $cmd('DATA', '354')
            && $cmd($data . "\r\n.", '250');

        @fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return $ok;
    }

    private static function headers(string $replyTo, string $boundary): array
    {
        $headers = [
            'From: ' . self::encodeHeader(self::fromName()) . ' <' . self::fromAddress() . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: iOiO',
        ];
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        return $headers;
    }

    private static function multipart(string $boundary, string $text, string $html): string
    {
        return "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $text . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $html . "\r\n\r\n"
            . "--{$boundary}--\r\n";
    }

    private static function encodeHeader(string $value): string
    {
        return preg_match('/[\x80-\xFF]/', $value) === 1
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }

    /** Gabarit HTML aux couleurs de la marque (flat, bordures noires). */
    private static function wrap(string $title, string $content): string
    {
        return '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . Text::e($title) . '</title></head>'
            . '<body style="margin:0;background:#FFF8EA;font-family:Manrope,Helvetica,Arial,sans-serif;color:#0E0E0E">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8EA;padding:28px 16px">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border:2px solid #0E0E0E;border-radius:22px;overflow:hidden">'
            . '<tr><td style="background:#0E0E0E;color:#FFF8EA;padding:22px 26px;font-size:20px;font-weight:800;letter-spacing:-.02em">Le iOiO</td></tr>'
            . '<tr><td style="padding:26px;font-size:15px;line-height:1.6">'
            . str_replace(
                ['<p>', '<p class="muted">', '<a class="btn"'],
                ['<p style="margin:0 0 14px">', '<p style="margin:0 0 14px;opacity:.65;font-size:13px">', '<a style="display:inline-block;padding:14px 22px;border-radius:999px;background:#FFD100;color:#0E0E0E;font-weight:800;text-decoration:none"'],
                $content
            )
            . '</td></tr>'
            . '<tr><td style="padding:18px 26px;border-top:2px solid #0E0E0E;background:#FFF8EA;font-size:12px;opacity:.7">'
            . Text::e(self::fromName()) . ' — ' . Text::e(Config::baseUrl())
            . '</td></tr></table></td></tr></table></body></html>';
    }
}
