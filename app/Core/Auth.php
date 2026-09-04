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

    /**
     * Au bout de deux heures sans rien faire, la session est close.
     *
     * Le poste du secrétariat d'une mairie est dans une pièce où passent des
     * administrés, et le navigateur y reste ouvert d'un jour sur l'autre. Sans
     * cette borne, une session vivait aussi longtemps que le navigateur —
     * c'est-à-dire indéfiniment. Deux heures laissent le temps de rédiger un
     * compte-rendu sans être interrompu.
     */
    private const INACTIVITE_SEC = 7200;

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
        $this->ecrireCompte($donnees);
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
            $this->ecrireCompte($compte);
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

        $this->ecrireCompte($compte);

        Session::regenerer();
        Session::set('admin', [
            'identifiant' => $identifiant,
            'depuis'      => time(),
            'vu_le'       => time(),
        ]);
    }

    public function connecter(string $identifiant): void
    {
        // L'identifiant de session change à la connexion : un cookie capté
        // avant elle ne vaut plus rien après.
        Session::regenerer();
        Session::set('admin', [
            'identifiant' => $identifiant,
            'depuis'      => time(),
            'vu_le'       => time(),
        ]);
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

        $vu = (int) ($admin['vu_le'] ?? $admin['depuis'] ?? 0);
        if ($vu > 0 && time() - $vu > self::INACTIVITE_SEC) {
            $this->deconnecter();
            return false;
        }

        // L'horodatage est repoussé à chaque écran : c'est l'inactivité qui
        // ferme la session, pas sa durée totale.
        $admin['vu_le'] = time();
        Session::set('admin', $admin);

        return true;
    }

    public function identifiant(): string
    {
        return Session::get('admin')['identifiant'] ?? '';
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
        self::ecrireAtomique($this->fichierTentatives, json_encode($liste) ?: '{}');
    }

    /** @param array<string, mixed> $compte */
    private function ecrireCompte(array $compte): void
    {
        self::ecrireAtomique(
            $this->fichierCompte,
            json_encode($compte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
        );
        @chmod($this->fichierCompte, Permissions::SECRET);
    }

    /**
     * Temporaire puis `rename()`, comme partout ailleurs dans ce dossier.
     *
     * Le compte administrateur était le seul fichier écrit en place. Une
     * écriture interrompue — disque plein, quota d'hébergement atteint,
     * processus tué — laissait un JSON tronqué, donc un compte illisible,
     * donc un back-office qui repropose l'écran de première configuration et
     * accepte de créer un nouveau compte. Le renommage est atomique : le
     * fichier est l'ancien en entier, ou le nouveau en entier.
     */
    private static function ecrireAtomique(string $fichier, string $contenu): void
    {
        $temporaire = $fichier . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporaire, $contenu, LOCK_EX) === false
            || !@rename($temporaire, $fichier)) {
            @unlink($temporaire);
            throw new \RuntimeException('Écriture impossible : ' . basename($fichier));
        }
    }
}
