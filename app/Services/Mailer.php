<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Audit;
use App\Storage\Json;

/**
 * Envoi d'e-mails transactionnels via mail() — aucune dépendance supplémentaire.
 * Si l'envoi échoue (hébergement sans MTA), le message est écrit dans
 * data/logs/mail/ pour rester récupérable, et l'appelant en est informé.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $textBody): bool
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $from = (string) Config::secret('mail_from', 'no-reply@intermittent.fr');
        $site = (string) Config::get('site.name');

        $headers = implode("\r\n", [
            'From: ' . self::encodeHeader($site) . ' <' . $from . '>',
            'Reply-To: ' . (string) Config::get('site.email', $from),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: intermittent.fr',
            'Auto-Submitted: auto-generated',
        ]);

        $ok = function_exists('mail')
            && @mail($to, self::encodeHeader($subject), self::wrap($textBody), $headers);

        self::archive($to, $subject, $textBody, $ok);
        Audit::log($ok ? 'mail.sent' : 'mail.failed', ['subject' => $subject]);

        return $ok;
    }

    /** Copie locale : un lien de récupération n'est jamais perdu faute de MTA. */
    private static function archive(string $to, string $subject, string $body, bool $sent): void
    {
        $dir = Config::path('data') . '/logs/mail';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        Json::write($dir . '/' . date('Ymd-His') . '-' . substr(sha1($to . $subject), 0, 8) . '.json', [
            'at'      => date('c'),
            'to'      => $to,
            'subject' => $subject,
            'body'    => $body,
            'sent'    => $sent,
        ]);
    }

    private static function encodeHeader(string $value): string
    {
        return preg_match('/[\x80-\xFF]/', $value) === 1
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }

    private static function wrap(string $body): string
    {
        return wordwrap(str_replace(["\r\n", "\r"], "\n", $body), 78, "\n", false);
    }

    /** Message de récupération de mot de passe. */
    public static function resetLink(string $to, string $link): bool
    {
        $site = (string) Config::get('site.name');
        $body = <<<TXT
        Bonjour,

        Une réinitialisation de mot de passe a été demandée pour votre compte sur {$site}.

        Ouvrez ce lien dans les 30 minutes pour choisir un nouveau mot de passe :
        {$link}

        Le lien ne fonctionne qu'une seule fois. Si vous n'êtes pas à l'origine de cette
        demande, ignorez ce message : votre mot de passe actuel reste valable.

        — {$site}
        TXT;

        return self::send($to, 'Réinitialisation de votre mot de passe', $body);
    }
}
