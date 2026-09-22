<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Audit;
use App\Storage\Json;

/**
 * Envoi d'e-mails transactionnels, sans dépendance.
 *
 * Deux transports : `mail()` par défaut, qui suffit tant que l'hébergement
 * porte un MTA, et un client SMTP authentifié dès qu'un serveur est renseigné
 * dans le back-office — ce qui vaut mieux pour la délivrabilité. Un échec est
 * signalé à l'appelant et laisse une trace expurgée dans data/logs/mail/.
 */
final class Mailer
{
    private static string $lastError = '';

    public static function lastError(): string
    {
        return self::$lastError;
    }

    /** Transport réellement utilisé : « smtp » ou « mail ». */
    public static function transport(): string
    {
        return Smtp::configured() ? 'smtp' : 'mail';
    }

    /**
     * @param string|string[] $to
     * @param string          $replyTo     adresse de réponse, si différente du site
     * @param array{name:string,mime:string,content:string}[] $attachments
     * @param bool $sensitive message relayant des données personnelles : en cas
     *                        d'échec, seul le fait est tracé, jamais le corps
     */
    public static function send(
        string|array $to,
        string $subject,
        string $textBody,
        string $replyTo = '',
        array $attachments = [],
        bool $sensitive = false,
    ): bool {
        self::$lastError = '';

        $recipients = [];
        foreach ((array) $to as $address) {
            $address = trim((string) $address);
            if (filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
                $recipients[] = $address;
            }
        }
        if ($recipients === []) {
            self::$lastError = 'Aucun destinataire valide.';
            return false;
        }

        $from = (string) Config::secret('mail_from', 'no-reply@intermittent.fr');
        $site = (string) Config::get('site.name');
        $replyTo = filter_var(trim($replyTo), FILTER_VALIDATE_EMAIL) !== false
            ? trim($replyTo)
            : (string) Config::get('site.email', $from);

        $lines = [
            'From: ' . self::encodeHeader($site) . ' <' . $from . '>',
            'Reply-To: ' . $replyTo,
            'MIME-Version: 1.0',
            'X-Mailer: intermittent.fr',
            'Auto-Submitted: auto-generated',
        ];

        if ($attachments === []) {
            $lines[] = 'Content-Type: text/plain; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: 8bit';
            $body = self::wrap($textBody);
        } else {
            $boundary = 'imtt-' . bin2hex(random_bytes(12));
            $lines[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
            $body = self::multipart($boundary, self::wrap($textBody), $attachments);
        }
        $encodedSubject = self::encodeHeader($subject);

        if (Smtp::configured()) {
            $headers = implode("\r\n", array_merge([
                'Date: ' . date('r'),
                'To: ' . implode(', ', $recipients),
                'Subject: ' . $encodedSubject,
            ], $lines));
            $ok = Smtp::send($recipients, $headers, $body, $from);
            if (!$ok) {
                self::$lastError = Smtp::lastError();
            }
        } else {
            $ok = function_exists('mail')
                && @mail(implode(', ', $recipients), $encodedSubject, $body, implode("\r\n", $lines));
            if (!$ok) {
                self::$lastError = function_exists('mail')
                    ? 'La fonction mail() a refusé le message : aucun MTA sur cet hébergement ?'
                    : 'La fonction mail() est désactivée sur ce serveur.';
            }
        }

        self::archive(implode(', ', $recipients), $subject, $textBody, $ok, $sensitive);
        Audit::log($ok ? 'mail.sent' : 'mail.failed', [
            'subject'   => $subject,
            'transport' => self::transport(),
        ]);

        return $ok;
    }

    /**
     * Trace des envois ratés, pour diagnostiquer un hébergement sans MTA.
     *
     * Un envoi réussi n'est pas archivé : conserver le corps de chaque message
     * reviendrait à garder une copie durable de données personnelles, et
     * surtout des liens de récupération — quiconque lirait le dossier
     * prendrait la main sur les comptes concernés. Les jetons sont donc
     * expurgés même dans la trace d'échec ; en l'absence de MTA, un mot de
     * passe se réinitialise depuis le serveur avec `php bin/create-admin.php`.
     */
    private static function archive(
        string $to,
        string $subject,
        string $body,
        bool $sent,
        bool $sensitive = false,
    ): void {
        if ($sent) {
            return;
        }

        $dir = Config::path('data') . '/logs/mail';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $file = $dir . '/' . date('Ymd-His') . '-' . substr(sha1($to . $subject), 0, 8) . '.json';
        $ok = Json::write($file, [
            'at'      => date('c'),
            'to'      => $to,
            'subject' => $subject,
            // Une candidature ou un message à un candidat ne laisse jamais son
            // contenu sur le disque, même quand l'envoi échoue.
            'body'    => $sensitive ? '[contenu non conservé]' : self::redact($body),
            'sent'    => false,
        ]);
        if ($ok) {
            @chmod($file, 0600);
        }
    }

    /** Retire d'un corps de message tout ce qui vaut identifiant. */
    public static function redact(string $body): string
    {
        $body = (string) preg_replace('/([?&](?:token|jeton|key)=)[^&\s]+/i', '$1***', $body);
        return (string) preg_replace('/\b[a-f0-9]{32,}\b/i', '***', $body);
    }

    /**
     * Supprime les traces d'échec passées. Appelé par bin/cron.php.
     *
     * @return int nombre de fichiers supprimés
     */
    public static function purge(int $days = 7): int
    {
        $limit = time() - max(1, $days) * 86400;
        $removed = 0;
        foreach (glob(Config::path('data') . '/logs/mail/*.json') ?: [] as $file) {
            if ((int) @filemtime($file) < $limit) {
                $removed += @unlink($file) ? 1 : 0;
            }
        }
        return $removed;
    }

    /**
     * Corps multipart : le texte, puis chaque pièce jointe en base64.
     *
     * @param array{name:string,mime:string,content:string}[] $attachments
     * @param bool $sensitive message relayant des données personnelles : en cas
     *                        d'échec, seul le fait est tracé, jamais le corps
     */
    private static function multipart(string $boundary, string $text, array $attachments): string
    {
        $out = "Cette partie du message n'est lisible qu'avec un client compatible MIME.\r\n\r\n"
             . '--' . $boundary . "\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 8bit\r\n\r\n"
             . str_replace("\n", "\r\n", $text) . "\r\n\r\n";

        foreach ($attachments as $file) {
            $name = preg_replace('/[^\w\.\- ]/u', '_', (string) ($file['name'] ?? 'piece-jointe'));
            $out .= '--' . $boundary . "\r\n"
                  . 'Content-Type: ' . ((string) ($file['mime'] ?? 'application/octet-stream'))
                  . '; name="' . $name . "\"\r\n"
                  . "Content-Transfer-Encoding: base64\r\n"
                  . 'Content-Disposition: attachment; filename="' . $name . "\"\r\n\r\n"
                  . chunk_split(base64_encode((string) ($file['content'] ?? '')), 76, "\r\n") . "\r\n";
        }

        return $out . '--' . $boundary . "--\r\n";
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
