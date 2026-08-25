<?php
declare(strict_types=1);

/**
 * Envoi d'e-mails sans dépendance externe (fonction mail() native).
 * Chaque envoi est journalisé dans data/maillog.json, ce qui permet
 * de tout retrouver depuis le back-office même si le serveur SMTP
 * n'est pas configuré.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody, ?string $replyTo = null): bool
    {
        $from = (string) settings('company.email', 'contact@suisse-immo.fr');
        $host = (string) (parse_url((string) settings('site.url', ''), PHP_URL_HOST) ?: 'suisse-immo.fr');
        $boundaryFrom = 'Suisse Immo <no-reply@' . $host . '>';

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $boundaryFrom,
            'Reply-To: ' . ($replyTo ?: $from),
            'X-Mailer: SuisseImmo-Funnel',
        ];

        $sent = false;
        if (settings('funnel.notify_enabled', true) && function_exists('mail')) {
            $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', self::wrap($subject, $htmlBody), implode("\r\n", $headers));
        }

        Store::mutate('maillog', static function (array $rows) use ($to, $subject, $sent, $htmlBody): array {
            array_unshift($rows, [
                'id' => Store::uid('mail-'),
                'to' => $to,
                'subject' => $subject,
                'sent' => $sent,
                'body' => $htmlBody,
                'created_at' => date('c'),
            ]);
            return array_slice($rows, 0, 300);
        });

        return $sent;
    }

    private static function wrap(string $title, string $body): string
    {
        $company = e((string) settings('company.legal_name', 'Suisse Immo'));
        return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>' . e($title) . '</title></head>'
            . '<body style="margin:0;background:#0e1017;font-family:Helvetica,Arial,sans-serif;color:#f2f4f8">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#14161f;border-radius:16px;overflow:hidden">'
            . '<tr><td style="padding:24px 28px;background:linear-gradient(120deg,#E62F43,#FF7A3D)"><strong style="font-size:18px;letter-spacing:.02em">' . $company . '</strong></td></tr>'
            . '<tr><td style="padding:28px;font-size:15px;line-height:1.65;color:#dfe3ec">' . $body . '</td></tr>'
            . '<tr><td style="padding:18px 28px;background:#0b0d13;font-size:12px;color:#8d99ae">'
            . e((string) settings('company.address')) . ' — ' . e((string) settings('company.zip')) . ' ' . e((string) settings('company.city'))
            . ' · ' . e((string) settings('company.phone')) . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
