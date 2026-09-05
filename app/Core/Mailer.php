<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Envoi d'e-mails.
 *
 * Client SMTP écrit sur des sockets natives (AUTH LOGIN, STARTTLS ou SSL
 * implicite) : aucune dépendance à installer. Si le SMTP n'est pas
 * configuré, bascule sur la fonction mail() de PHP.
 */
final class Mailer
{
    /** @var string[] trace de la dernière conversation SMTP, pour diagnostic */
    private array $journal = [];

    public function __construct(private readonly Parametres $parametres)
    {
    }

    /** @return string[] */
    public function journal(): array
    {
        return $this->journal;
    }

    /**
     * @param string $repondreA adresse de réponse (l'expéditeur reste le compte SMTP)
     * @throws RuntimeException si l'envoi échoue
     */
    public function envoyer(
        string $destinataire,
        string $sujet,
        string $corps,
        string $repondreA = '',
        string $nomRepondreA = '',
    ): void {
        $this->journal = [];

        if ($destinataire === '' || !filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Destinataire invalide : « ' . $destinataire . ' ».');
        }

        $expediteur = (string) $this->parametres->get('smtp.expediteur') ?: $this->expediteurParDefaut();
        $nom        = trim((string) $this->parametres->get('smtp.nom_expediteur', '')) ?: nom_du_site();

        if (!$this->parametres->smtpConfigure()) {
            $this->envoyerParMail($destinataire, $sujet, $corps, $expediteur, $nom, $repondreA);
            return;
        }

        $entetes = $this->entetes($expediteur, $nom, $destinataire, $sujet, $repondreA, $nomRepondreA);
        $this->envoyerParSmtp($expediteur, $destinataire, $entetes . "\r\n" . $this->normaliser($corps));
    }

    // ------------------------------------------------------------ transports

    private function envoyerParMail(
        string $destinataire,
        string $sujet,
        string $corps,
        string $expediteur,
        string $nom,
        string $repondreA,
    ): void {
        $entetes = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Language: fr',
        ];
        if ($expediteur !== '') {
            $entetes[] = 'From: ' . $this->adresse($nom, $expediteur);
        }
        if ($repondreA !== '') {
            $entetes[] = 'Reply-To: ' . $repondreA;
        }

        $ok = @mail(
            $destinataire,
            $this->encoderSujet($sujet),
            $this->normaliser($corps),
            implode("\r\n", $entetes)
        );
        $this->journal[] = 'Transport : fonction mail() de PHP';

        if (!$ok) {
            throw new RuntimeException(
                'La fonction mail() de PHP a échoué. Configurez le SMTP dans Paramètres.'
            );
        }
    }

    private function envoyerParSmtp(string $expediteur, string $destinataire, string $message): void
    {
        $hote     = (string) $this->parametres->get('smtp.hote');
        $port     = (int) $this->parametres->get('smtp.port', 587);
        $securite = (string) $this->parametres->get('smtp.securite', 'tls');
        $identifiant = (string) $this->parametres->get('smtp.identifiant');
        $motDePasse  = (string) $this->parametres->get('smtp.mot_de_passe');

        $adresse = ($securite === 'ssl' ? 'ssl://' : '') . $hote . ':' . $port;
        $contexte = stream_context_create([
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true],
        ]);

        $flux = @stream_socket_client(
            $adresse,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $contexte
        );
        if ($flux === false) {
            throw new RuntimeException("Connexion à {$hote}:{$port} impossible ({$errstr}).");
        }
        stream_set_timeout($flux, 15);

        try {
            $this->lire($flux, 220);

            $domaine = $this->domaineLocal($expediteur);
            $this->commande($flux, 'EHLO ' . $domaine, 250);

            if ($securite === 'tls') {
                $this->commande($flux, 'STARTTLS', 220);
                $chiffre = @stream_socket_enable_crypto(
                    $flux,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                );
                if ($chiffre !== true) {
                    throw new RuntimeException('Passage en TLS refusé par le serveur.');
                }
                $this->journal[] = '--- connexion chiffrée (STARTTLS) ---';
                $this->commande($flux, 'EHLO ' . $domaine, 250);
            }

            if ($identifiant !== '') {
                $this->commande($flux, 'AUTH LOGIN', 334);
                $this->commande($flux, base64_encode($identifiant), 334, '[identifiant]');
                $this->commande($flux, base64_encode($motDePasse), 235, '[mot de passe]');
            }

            $this->commande($flux, 'MAIL FROM:<' . $expediteur . '>', 250);
            $this->commande($flux, 'RCPT TO:<' . $destinataire . '>', [250, 251]);
            $this->commande($flux, 'DATA', 354);

            // point seul en début de ligne = fin de message : on l'échappe
            $corps = preg_replace('/^\./m', '..', $message) ?? $message;
            fwrite($flux, $corps . "\r\n.\r\n");
            $this->journal[] = '> [message, ' . strlen($corps) . ' octets]';
            $this->lire($flux, 250);

            $this->commande($flux, 'QUIT', [221, 250]);
        } finally {
            fclose($flux);
        }
    }

    // --------------------------------------------------------------- dialogue

    /**
     * @param int|int[] $attendu
     */
    private function commande($flux, string $ligne, int|array $attendu, ?string $masque = null): string
    {
        fwrite($flux, $ligne . "\r\n");
        $this->journal[] = '> ' . ($masque ?? $ligne);
        return $this->lire($flux, $attendu);
    }

    /**
     * @param int|int[] $attendu
     */
    private function lire($flux, int|array $attendu): string
    {
        $reponse = '';
        while (($ligne = fgets($flux, 515)) !== false) {
            $reponse .= $ligne;
            // dernière ligne d'une réponse multiligne : « 250 » et non « 250- »
            if (strlen($ligne) < 4 || $ligne[3] !== '-') {
                break;
            }
        }

        $reponse = trim($reponse);
        $this->journal[] = '< ' . $reponse;

        $code = (int) substr($reponse, 0, 3);
        $codes = is_array($attendu) ? $attendu : [$attendu];
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('Réponse SMTP inattendue : ' . ($reponse ?: 'aucune réponse'));
        }

        return $reponse;
    }

    // ----------------------------------------------------------------- entêtes

    private function entetes(
        string $expediteur,
        string $nom,
        string $destinataire,
        string $sujet,
        string $repondreA,
        string $nomRepondreA,
    ): string {
        $lignes = [
            'Date: ' . date('r'),
            'From: ' . $this->adresse($nom, $expediteur),
            'To: <' . $destinataire . '>',
            'Subject: ' . $this->encoderSujet($sujet),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $this->domaineLocal($expediteur) . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            // sans cette ligne, les messageries devinent la langue d'après le
            // texte, et se trompent sur un message court et très étiqueté
            'Content-Language: fr',
        ];
        if ($repondreA !== '' && filter_var($repondreA, FILTER_VALIDATE_EMAIL)) {
            $lignes[] = 'Reply-To: ' . $this->adresse($nomRepondreA, $repondreA);
        }
        return implode("\r\n", $lignes) . "\r\n";
    }

    private function adresse(string $nom, string $email): string
    {
        $nom = trim($nom);
        return $nom === ''
            ? '<' . $email . '>'
            : $this->encoderSujet($nom) . ' <' . $email . '>';
    }

    /**
     * Encodage RFC 2047 si le texte sort de l'ASCII.
     */
    private function encoderSujet(string $texte): string
    {
        $texte = str_replace(["\r", "\n"], ' ', $texte);
        return preg_match('/[\x80-\xFF]/', $texte)
            ? '=?UTF-8?B?' . base64_encode($texte) . '?='
            : $texte;
    }

    private function normaliser(string $corps): string
    {
        return preg_replace('/\R/u', "\r\n", $corps) ?? $corps;
    }

    /**
     * Expéditeur de repli quand aucun SMTP n'est réglé : une adresse du
     * domaine servant le site, pour que mail() produise un From valide.
     */
    private function expediteurParDefaut(): string
    {
        $hote = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $hote = strtolower(preg_replace('/:\d+$/', '', $hote) ?? '');
        $hote = preg_replace('/^www\./', '', $hote) ?? $hote;

        return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $hote)
            ? 'nepasrepondre@' . $hote
            : '';
    }

    private function domaineLocal(string $expediteur): string
    {
        $domaine = substr(strrchr($expediteur, '@') ?: '', 1);
        return $domaine !== '' ? $domaine : ($_SERVER['SERVER_NAME'] ?? 'localhost');
    }
}
