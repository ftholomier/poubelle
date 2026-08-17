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
            mkdir($dossier, 0770, true);
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
        @chmod($this->fichierCompte, 0640);
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
        @chmod($this->fichierCompte, 0640);

        Session::regenerer();
        Session::set('admin', ['identifiant' => $identifiant, 'depuis' => time()]);
    }

    public function connecter(string $identifiant): void
    {
        Session::regenerer();
        Session::set('admin', ['identifiant' => $identifiant, 'depuis' => time()]);
    }

    public function deconnecter(): void
    {
        Session::detruire();
    }

    public function connecte(): bool
    {
        return is_array(Session::get('admin'));
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
            mkdir($dossier, 0770, true);
        }
        file_put_contents($this->fichierTentatives, json_encode($liste), LOCK_EX);
    }
}
