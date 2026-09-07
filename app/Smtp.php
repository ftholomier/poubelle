<?php
declare(strict_types=1);

/**
 * Client SMTP minimal, sans aucune dépendance.
 *
 * La fonction mail() de PHP dépend d'un agent local (sendmail) que la
 * plupart des hébergements mutualisés ne fournissent pas, ou dont les
 * messages finissent en indésirables faute d'authentification. Ce client
 * parle directement à un serveur SMTP authentifié : la candidature part
 * réellement, avec un expéditeur légitime.
 *
 * Chiffrement pris en charge : aucun, STARTTLS, ou TLS implicite (465).
 * Authentification : AUTH LOGIN et AUTH PLAIN.
 */
final class Smtp
{
    private const CRLF = "\r\n";

    /** @var resource|null */
    private $flux = null;
    private string $journal = '';

    public function __construct(
        private string $hote,
        private int $port = 587,
        private string $chiffrement = 'tls',
        private string $utilisateur = '',
        private string $motDePasse = '',
        private int $delai = 12,
    ) {
    }

    /** Dernier dialogue avec le serveur : précieux pour diagnostiquer. */
    public function journal(): string
    {
        return $this->journal;
    }

    /**
     * Envoie un message HTML.
     *
     * @param array<int,string> $enTetes en-têtes supplémentaires déjà formés
     * @throws RuntimeException si le serveur refuse une étape du dialogue
     */
    public function envoyer(string $deEmail, string $vers, string $sujet, string $corpsHtml, array $enTetes = []): bool
    {
        $this->connecter();
        try {
            $nomLocal = (string) (parse_url((string) settings('site.url', ''), PHP_URL_HOST) ?: 'localhost');
            $this->commande('EHLO ' . $nomLocal, [250]);

            if ($this->chiffrement === 'tls') {
                $this->commande('STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($this->flux, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Passage en TLS refusé par ' . $this->hote);
                }
                $this->commande('EHLO ' . $nomLocal, [250]);
            }

            if ($this->utilisateur !== '') {
                $this->authentifier();
            }

            $this->commande('MAIL FROM:<' . $deEmail . '>', [250]);
            $this->commande('RCPT TO:<' . $vers . '>', [250, 251]);
            $this->commande('DATA', [354]);

            $donnees = implode(self::CRLF, $enTetes) . self::CRLF . self::CRLF . $corpsHtml;
            // Un point seul en début de ligne terminerait le message.
            $donnees = preg_replace('/^\./m', '..', $donnees) ?? $donnees;
            $this->ecrire($donnees . self::CRLF . '.');
            $this->lireReponse([250]);

            $this->commande('QUIT', [221]);
            return true;
        } finally {
            $this->fermer();
        }
    }

    private function connecter(): void
    {
        $prefixe = $this->chiffrement === 'ssl' ? 'ssl://' : '';
        $contexte = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $flux = @stream_socket_client(
            $prefixe . $this->hote . ':' . $this->port,
            $code,
            $message,
            $this->delai,
            STREAM_CLIENT_CONNECT,
            $contexte
        );
        if ($flux === false) {
            throw new RuntimeException('Connexion SMTP impossible vers ' . $this->hote . ':' . $this->port . ' — ' . $message);
        }
        stream_set_timeout($flux, $this->delai);
        $this->flux = $flux;
        $this->lireReponse([220]);
    }

    private function authentifier(): void
    {
        // AUTH LOGIN est le plus répandu ; AUTH PLAIN sert de repli.
        try {
            $this->commande('AUTH LOGIN', [334]);
            $this->commande(base64_encode($this->utilisateur), [334]);
            $this->commande(base64_encode($this->motDePasse), [235]);
        } catch (RuntimeException $e) {
            $this->commande(
                'AUTH PLAIN ' . base64_encode("\0" . $this->utilisateur . "\0" . $this->motDePasse),
                [235]
            );
        }
    }

    /** @param array<int,int> $attendus */
    private function commande(string $ligne, array $attendus): string
    {
        $this->ecrire($ligne);
        return $this->lireReponse($attendus);
    }

    private function ecrire(string $ligne): void
    {
        // Le mot de passe ne doit pas se retrouver dans le journal.
        $this->journal .= '> ' . (str_starts_with($ligne, 'AUTH') ? 'AUTH …' : $ligne) . "\n";
        if (@fwrite($this->flux, $ligne . self::CRLF) === false) {
            throw new RuntimeException('Écriture impossible sur la connexion SMTP.');
        }
    }

    /** @param array<int,int> $attendus */
    private function lireReponse(array $attendus): string
    {
        $reponse = '';
        while (($ligne = fgets($this->flux, 1024)) !== false) {
            $reponse .= $ligne;
            // Les réponses multi-lignes utilisent « 250- » ; « 250 » clôt.
            if (strlen($ligne) < 4 || $ligne[3] !== '-') {
                break;
            }
        }
        $this->journal .= '< ' . trim($reponse) . "\n";
        $code = (int) substr(trim($reponse), 0, 3);
        if (!in_array($code, $attendus, true)) {
            throw new RuntimeException('Réponse SMTP inattendue (' . $code . ') : ' . trim($reponse));
        }
        return $reponse;
    }

    private function fermer(): void
    {
        if (is_resource($this->flux)) {
            @fclose($this->flux);
        }
        $this->flux = null;
    }
}
