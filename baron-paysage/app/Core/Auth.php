<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Authentification du back-office.
 *
 * Le compte vit dans data/admin/compte.json (hors git, hors racine web).
 * Tant qu'il n'existe pas, l'écran de première configuration est proposé.
 * Verrouillage temporaire après plusieurs échecs consécutifs par IP.
 */
final class Auth
{
    private const MAX_ECHECS   = 5;
    private const VERROU_SEC   = 900;   // 15 minutes
    private const FENETRE_SEC  = 3600;

    public function __construct(
        private readonly string $fichierCompte,
        private readonly string $fichierTentatives,
    ) {
    }

    public function compteExiste(): bool
    {
        return is_file($this->fichierCompte);
    }

    /**
     * Première configuration : crée l'unique compte administrateur.
     */
    public function creerCompte(string $identifiant, string $motDePasse): void
    {
        $dossier = dirname($this->fichierCompte);
        if (!is_dir($dossier)) {
            $ancien = umask(0);
            @mkdir($dossier, Permissions::DOSSIER, true);
            umask($ancien);
        }
        $donnees = [
            'identifiant' => $identifiant,
            'hash'        => password_hash($motDePasse, PASSWORD_DEFAULT),
            'cree_le'     => date('c'),
        ];
        file_put_contents(
            $this->fichierCompte,
            json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
            LOCK_EX
        );
        @chmod($this->fichierCompte, Permissions::SECRET);
    }

    public function verifier(string $identifiant, string $motDePasse): bool
    {
        if (!$this->compteExiste()) {
            return false;
        }
        $compte = json_decode((string) file_get_contents($this->fichierCompte), true);
        if (!is_array($compte)) {
            return false;
        }
        $ok = hash_equals($compte['identifiant'] ?? '', $identifiant)
            && password_verify($motDePasse, $compte['hash'] ?? '');

        if ($ok && password_needs_rehash($compte['hash'], PASSWORD_DEFAULT)) {
            $compte['hash'] = password_hash($motDePasse, PASSWORD_DEFAULT);
            file_put_contents(
                $this->fichierCompte,
                json_encode($compte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
                LOCK_EX
            );
        }
        return $ok;
    }

    /**
     * Change l'identifiant et/ou le mot de passe du compte existant.
     * La session est régénérée pour invalider un éventuel vol de cookie.
     */
    public function modifierCompte(string $identifiant, string $nouveauMotDePasse): void
    {
        if (!$this->compteExiste()) {
            throw new \RuntimeException('Aucun compte à modifier.');
        }
        $compte = json_decode((string) file_get_contents($this->fichierCompte), true);
        if (!is_array($compte)) {
            throw new \RuntimeException('Compte illisible.');
        }

        $compte['identifiant'] = $identifiant;
        if ($nouveauMotDePasse !== '') {
            $compte['hash'] = password_hash($nouveauMotDePasse, PASSWORD_DEFAULT);
        }
        $compte['modifie_le'] = date('c');

        file_put_contents(
            $this->fichierCompte,
            json_encode($compte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
            LOCK_EX
        );
        @chmod($this->fichierCompte, Permissions::SECRET);

        $this->ouvrirSession($identifiant);
    }

    public function connecter(string $identifiant): void
    {
        $this->ouvrirSession($identifiant);
    }

    /**
     * Ouvre une session d'administration scellée au mot de passe courant.
     *
     * Le sceau est un condensat du hash : il change dès que le mot de passe
     * change, si bien qu'une session ne vaut que tant que le mot de passe qui
     * l'a ouverte tient. Le changer — par les Paramètres ou par une
     * récupération — invalide donc TOUTES les sessions ouvertes, y compris
     * dans un autre navigateur et celle d'un tiers qui aurait connu l'ancien.
     * C'est ce que « détruire la session » ne fait pas : une session PHP est
     * liée à un cookie, en détruire une ne touche pas les autres.
     */
    private function ouvrirSession(string $identifiant): void
    {
        Session::regenerer();
        Session::set('admin', [
            'identifiant' => $identifiant,
            'depuis'      => time(),
            'sceau'       => $this->sceau(),
        ]);
    }

    /**
     * Condensat court du hash du mot de passe : il identifie le mot de passe
     * courant sans l'exposer, et sert de sceau aux sessions.
     */
    private function sceau(): string
    {
        $compte = $this->compte();

        return $compte === null ? '' : substr(hash('sha256', (string) ($compte['hash'] ?? '')), 0, 16);
    }

    public function deconnecter(): void
    {
        Session::detruire();
    }

    public function connecte(): bool
    {
        $admin = Session::get('admin');
        if (!is_array($admin)) {
            return false;
        }

        // Le sceau doit correspondre au mot de passe courant : une session
        // ouverte sous l'ancien mot de passe ne vaut plus rien.
        return isset($admin['sceau']) && hash_equals($this->sceau(), (string) $admin['sceau']);
    }

    public function identifiant(): string
    {
        return Session::get('admin')['identifiant'] ?? '';
    }

    /**
     * L'identifiant enregistré, ou une chaîne vide si aucun compte n'existe.
     */
    public function identifiantDuCompte(): string
    {
        return (string) ($this->compte()['identifiant'] ?? '');
    }

    // ----- récupération du mot de passe ----------------------------------

    /** Durée de validité d'un lien de récupération. */
    private const DUREE_JETON = 3600;

    /**
     * Fabrique un jeton de récupération, signé et daté.
     *
     * Rien n'est stocké : la signature porte sur l'empreinte du mot de passe
     * ACTUEL, si bien que le jeton cesse de valoir dès que le mot de passe
     * change. C'est ce qui le rend à usage unique sans registre à tenir — et
     * sans qu'une sauvegarde restaurée puisse ressusciter un lien périmé.
     */
    public function jetonRecuperation(): ?string
    {
        $compte = $this->compte();
        if ($compte === null) {
            return null;
        }

        $expiration = time() + self::DUREE_JETON;

        return $expiration . '.' . $this->signature($compte, $expiration);
    }

    /**
     * Vrai si le jeton vient de nous, n'a pas expiré, et n'a pas déjà servi.
     */
    public function jetonValide(string $jeton): bool
    {
        $compte = $this->compte();
        if ($compte === null) {
            return false;
        }

        $morceaux = explode('.', $jeton, 2);
        if (count($morceaux) !== 2 || !ctype_digit($morceaux[0])) {
            return false;
        }

        [$expiration, $signature] = $morceaux;
        if ((int) $expiration < time()) {
            return false;
        }

        return hash_equals($this->signature($compte, (int) $expiration), $signature);
    }

    /**
     * Remplace le mot de passe sans demander l'ancien. Réservé au parcours de
     * récupération, où le jeton fait office de preuve.
     */
    public function redefinirMotDePasse(string $motDePasse): void
    {
        $compte = $this->compte();
        if ($compte === null) {
            throw new \RuntimeException('Aucun compte à modifier.');
        }

        $compte['hash'] = password_hash($motDePasse, PASSWORD_DEFAULT);
        $compte['modifie_le'] = date('c');
        $this->ecrireCompte($compte);

        // Rien à détruire ici : le mot de passe ayant changé, son sceau aussi,
        // et toutes les sessions ouvertes cessent de valoir d'elles-mêmes.
    }

    /**
     * @param array<string, mixed> $compte
     */
    private function signature(array $compte, int $expiration): string
    {
        return hash_hmac(
            'sha256',
            ($compte['identifiant'] ?? '') . '|' . ($compte['hash'] ?? '') . '|' . $expiration,
            $this->secret($compte)
        );
    }

    /**
     * Secret de signature, créé au premier besoin et conservé avec le compte.
     *
     * Sa place est là et pas ailleurs : supprimer le fichier de compte — le
     * dépannage par FTP quand plus rien ne répond — invalide du même coup tout
     * lien de récupération en circulation.
     *
     * @param array<string, mixed> $compte
     */
    private function secret(array &$compte): string
    {
        if (($compte['secret'] ?? '') !== '') {
            return (string) $compte['secret'];
        }

        $compte['secret'] = bin2hex(random_bytes(32));
        $this->ecrireCompte($compte);

        return $compte['secret'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function compte(): ?array
    {
        if (!$this->compteExiste()) {
            return null;
        }
        $compte = json_decode((string) file_get_contents($this->fichierCompte), true);

        return is_array($compte) ? $compte : null;
    }

    /**
     * @param array<string, mixed> $compte
     */
    private function ecrireCompte(array $compte): void
    {
        // Écriture atomique : un fichier de compte tronqué par une écriture
        // interrompue ferme le back-office pour de bon.
        $tmp = $this->fichierCompte . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $json = json_encode($compte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $this->fichierCompte)) {
            @unlink($tmp);
            throw new \RuntimeException('Écriture impossible : compte.json');
        }
        @chmod($this->fichierCompte, Permissions::SECRET);
    }

    // ----- limitation des tentatives ------------------------------------

    public function verrouille(string $ip): ?int
    {
        $t = $this->tentatives()[$ip] ?? null;
        if ($t === null) {
            return null;
        }
        if ($t['echecs'] >= self::MAX_ECHECS && time() - $t['dernier'] < self::VERROU_SEC) {
            return self::VERROU_SEC - (time() - $t['dernier']);
        }
        return null;
    }

    public function noterEchec(string $ip): void
    {
        $liste = $this->tentatives();
        $t = $liste[$ip] ?? ['echecs' => 0, 'dernier' => 0];
        if (time() - $t['dernier'] > self::FENETRE_SEC) {
            $t['echecs'] = 0;
        }
        $t['echecs']++;
        $t['dernier'] = time();
        $liste[$ip] = $t;
        $this->ecrireTentatives($liste);
    }

    public function noterSucces(string $ip): void
    {
        $liste = $this->tentatives();
        unset($liste[$ip]);
        $this->ecrireTentatives($liste);
    }

    /** @return array<string, array{echecs:int, dernier:int}> */
    private function tentatives(): array
    {
        if (!is_file($this->fichierTentatives)) {
            return [];
        }
        $d = json_decode((string) file_get_contents($this->fichierTentatives), true);
        return is_array($d) ? $d : [];
    }

    /** @param array<string, array{echecs:int, dernier:int}> $liste */
    private function ecrireTentatives(array $liste): void
    {
        // purge des entrées expirées au passage
        foreach ($liste as $ip => $t) {
            if (time() - $t['dernier'] > self::FENETRE_SEC) {
                unset($liste[$ip]);
            }
        }
        $dossier = dirname($this->fichierTentatives);
        if (!is_dir($dossier)) {
            $ancien = umask(0);
            @mkdir($dossier, Permissions::DOSSIER, true);
            umask($ancien);
        }
        file_put_contents($this->fichierTentatives, json_encode($liste), LOCK_EX);
    }
}
