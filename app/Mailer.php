<?php
declare(strict_types=1);

/**
 * Envoi d'e-mails sans dépendance externe.
 *
 * Deux transports : un serveur SMTP authentifié dès qu'il est renseigné
 * dans les réglages (voir Smtp.php), sinon la fonction mail() locale.
 * Chaque envoi est journalisé dans data/maillog.json avec son transport
 * et l'éventuelle erreur, ce qui permet de tout retrouver depuis le
 * back-office même quand rien ne part.
 */
final class Mailer
{
    /** Nombre d'entrées conservées avec leur corps HTML complet. */
    private const CORPS_CONSERVES = 60;
    /** Nombre total d'entrées conservées dans le journal. */
    private const ENTREES_CONSERVEES = 300;

    /**
     * @param bool $obligatoire message de service (réinitialisation de mot
     *                          de passe, test d'envoi) : il part même si les
     *                          notifications sont désactivées, sans quoi
     *                          couper les notifications enfermerait
     *                          l'exploitant hors du back-office.
     */
    public static function send(string $to, string $subject, string $htmlBody, ?string $replyTo = null, bool $obligatoire = false): bool
    {
        $to = self::adresse($to);
        $from = self::adresse((string) settings('company.email', 'contact@suisse-immo.fr'));
        $replyTo = self::adresse((string) ($replyTo ?: $from));
        $subject = self::monoLigne($subject);
        $host = (string) (parse_url((string) settings('site.url', ''), PHP_URL_HOST) ?: 'suisse-immo.fr');
        $expediteur = (string) settings('mail.from', 'no-reply@' . $host);
        $expediteur = self::adresse($expediteur) ?: 'no-reply@' . $host;
        $nomExpediteur = self::monoLigne((string) settings('mail.from_name', 'Suisse Immo'));

        $corps = self::wrap($subject, $htmlBody);
        $enTetes = [
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::encoder($nomExpediteur) . ' <' . $expediteur . '>',
            'Reply-To: ' . $replyTo,
            'To: ' . $to,
            'Subject: ' . self::encoder($subject),
            'X-Mailer: SuisseImmo-Funnel',
        ];

        $sent = false;
        $erreur = '';
        $transport = 'désactivé';

        if ($to === '') {
            $erreur = 'Adresse destinataire invalide.';
        } elseif (!$obligatoire && !settings('funnel.notify_enabled', true)) {
            $erreur = 'Envoi désactivé dans les réglages.';
        } else {
            if (self::smtpConfigure()) {
                $transport = 'smtp';
                try {
                    $client = new Smtp(
                        (string) settings('mail.smtp_host', ''),
                        (int) settings('mail.smtp_port', 587),
                        (string) settings('mail.smtp_encryption', 'tls'),
                        (string) settings('mail.smtp_user', ''),
                        (string) settings('mail.smtp_password', ''),
                    );
                    $sent = $client->envoyer($expediteur, $to, $subject, $corps, $enTetes);
                } catch (Throwable $e) {
                    $erreur = $e->getMessage();
                    ErrorHandler::log($e);
                }
            } elseif (self::mailDisponible()) {
                // Repli : agent local. Les en-têtes To et Subject sont
                // passés en arguments, on ne les répète pas.
                $transport = 'mail()';
                $entetesMail = array_values(array_filter(
                    $enTetes,
                    static fn ($h) => !str_starts_with($h, 'To: ') && !str_starts_with($h, 'Subject: ')
                ));
                // Enveloppe d'expédition explicite : sans -f, l'agent local
                // signe avec l'utilisateur système (www-data@serveur), une
                // adresse qui n'existe pas et que les filtres rejettent.
                $enveloppe = escapeshellcmd('-f' . $expediteur);
                $sent = @mail($to, self::encoder($subject), $corps, implode("\r\n", $entetesMail), $enveloppe);
                if (!$sent) {
                    $erreur = self::diagnosticMail();
                }
            } else {
                $erreur = self::diagnosticMail();
            }
        }

        self::journaliser($to, $subject, $sent, $htmlBody, $transport, $erreur);

        return $sent;
    }

    /** La fonction mail() est-elle utilisable sur cet hébergement ? */
    public static function mailDisponible(): bool
    {
        if (!function_exists('mail')) {
            return false;
        }
        $desactivees = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return !in_array('mail', $desactivees, true);
    }

    /**
     * Explique pourquoi un envoi par mail() ne peut pas aboutir.
     *
     * « La fonction mail() a échoué » n'aide personne : selon les cas, la
     * fonction est désactivée par l'hébergeur, ou bien le programme
     * d'envoi déclaré dans sendmail_path n'existe pas sur la machine.
     */
    public static function diagnosticMail(): string
    {
        if (!function_exists('mail')) {
            return 'La fonction mail() n’existe pas sur cet hébergement. Renseignez un serveur SMTP.';
        }
        $desactivees = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('mail', $desactivees, true)) {
            return 'La fonction mail() est désactivée par l’hébergeur (disable_functions). Renseignez un serveur SMTP.';
        }
        $chemin = trim((string) ini_get('sendmail_path'));
        if ($chemin === '') {
            return 'Aucun programme d’envoi n’est configuré (sendmail_path vide). Renseignez un serveur SMTP.';
        }
        $binaire = explode(' ', $chemin)[0];
        if ($binaire !== '' && !is_executable($binaire)) {
            return 'Le programme d’envoi « ' . $binaire .' » est introuvable sur le serveur. Renseignez un serveur SMTP.';
        }
        return 'La fonction mail() a rendu la main sur un échec, sans motif. Le message a probablement été refusé par l’agent local.';
    }

    /** Un serveur SMTP est-il renseigné dans les réglages ? */
    public static function smtpConfigure(): bool
    {
        return trim((string) settings('mail.smtp_host', '')) !== '';
    }

    /**
     * Journal des envois.
     *
     * Le corps HTML complet n'est gardé que pour les envois récents : au
     * bout de quelques mois, trois cents messages entiers font grossir le
     * fichier pour rien, alors que l'objet et le destinataire suffisent à
     * retracer ce qui est parti.
     */
    private static function journaliser(string $to, string $subject, bool $sent, string $body, string $transport, string $erreur): void
    {
        Store::mutate('maillog', static function (array $rows) use ($to, $subject, $sent, $body, $transport, $erreur): array {
            array_unshift($rows, [
                'id' => Store::uid('mail-'),
                'to' => $to,
                'subject' => $subject,
                'sent' => $sent,
                'transport' => $transport,
                'error' => $erreur,
                'body' => $body,
                'created_at' => date('c'),
            ]);
            $rows = array_slice($rows, 0, self::ENTREES_CONSERVEES);
            foreach ($rows as $i => $row) {
                if ($i >= self::CORPS_CONSERVES && isset($row['body'])) {
                    $rows[$i]['body'] = '';
                    $rows[$i]['body_purged'] = true;
                }
            }
            return $rows;
        });
    }

    /**
     * Adresse e-mail nettoyée.
     *
     * Un retour à la ligne dans une adresse ou un objet permettrait
     * d'ajouter des en-têtes arbitraires (Bcc vers un tiers) : les
     * caractères de contrôle sont retirés avant toute utilisation.
     */
    private static function adresse(string $valeur): string
    {
        $valeur = trim(str_replace(["\r", "\n", "\0", "\t"], '', $valeur));
        return filter_var($valeur, FILTER_VALIDATE_EMAIL) ? $valeur : '';
    }

    private static function monoLigne(string $valeur): string
    {
        return trim(preg_replace('/[\r\n\0]+/', ' ', $valeur) ?? '');
    }

    /** Encodage RFC 2047 : l'objet peut contenir des accents. */
    private static function encoder(string $valeur): string
    {
        return preg_match('/[^\x20-\x7E]/', $valeur) === 1
            ? '=?UTF-8?B?' . base64_encode($valeur) . '?='
            : $valeur;
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
