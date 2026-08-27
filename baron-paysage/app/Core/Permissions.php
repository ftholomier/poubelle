<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Droits d'accès des fichiers et dossiers.
 *
 * Deux raisons de s'en occuper : les fichiers créés par PHP dépendent du
 * umask du serveur (imprévisible d'un hébergeur à l'autre), et git ne
 * restaure pas les droits lors d'une mise à jour.
 *
 * Cible : 0755 pour les dossiers, 0644 pour les fichiers, 0640 pour ce qui
 * contient un secret. Jamais 0777 — plusieurs hébergements mutualisés
 * refusent de servir un fichier accessible en écriture à tous.
 */
final class Permissions
{
    public const DOSSIER = 0755;
    public const FICHIER = 0644;
    public const SECRET  = 0640;

    /** Non parcourus : volumineux et sans incidence sur le site servi. */
    private const IGNORES = ['.git', 'node_modules', 'vendor'];

    /** Contiennent un mot de passe ou une empreinte de mot de passe. */
    private const SECRETS = ['data/admin', 'storage/deploiements'];

    /** Doivent rester inscriptibles, sinon le back-office ne peut plus écrire. */
    private const INSCRIPTIBLES = ['data', 'data/pages', 'storage', 'public/assets/img/site'];

    public function __construct(private readonly string $racine)
    {
    }

    // ------------------------------------------------------------------ analyse

    /**
     * @return array{anomalies:array<int, array{chemin:string, mode:string, probleme:string}>,
     *               examines:int, tronque:bool}
     */
    public function analyser(int $maximum = 25): array
    {
        $anomalies = [];
        $examines  = 0;

        foreach ($this->parcourir() as $chemin => $relatif) {
            $examines++;
            $mode = @fileperms($chemin);
            if ($mode === false) {
                continue;
            }
            $mode &= 0777;

            $probleme = $this->probleme($chemin, $relatif, $mode);
            if ($probleme !== null && count($anomalies) < $maximum) {
                $anomalies[] = [
                    'chemin'   => $relatif,
                    'mode'     => sprintf('%04o', $mode),
                    'probleme' => $probleme,
                ];
            }
        }

        // dossiers qui doivent rester inscriptibles
        foreach (self::INSCRIPTIBLES as $relatif) {
            $chemin = $this->racine . '/' . $relatif;
            if (is_dir($chemin) && !is_writable($chemin) && count($anomalies) < $maximum) {
                $anomalies[] = [
                    'chemin'   => $relatif,
                    'mode'     => sprintf('%04o', @fileperms($chemin) & 0777),
                    'probleme' => 'doit être inscriptible pour le back-office',
                ];
            }
        }

        return [
            'anomalies' => $anomalies,
            'examines'  => $examines,
            'tronque'   => count($anomalies) >= $maximum,
        ];
    }

    private function probleme(string $chemin, string $relatif, int $mode): ?string
    {
        if (($mode & 0002) !== 0) {
            return 'accessible en écriture à tout le monde';
        }
        if ($this->estSecret($relatif) && !is_dir($chemin) && ($mode & 0004) !== 0) {
            return 'contient un secret et reste lisible par tout le monde';
        }
        if (is_dir($chemin) && ($mode & 0700) !== 0700) {
            return 'dossier non parcourable par son propriétaire';
        }
        return null;
    }

    // ---------------------------------------------------------------- réparation

    /**
     * Remet tout l'arbre aux droits cibles.
     *
     * @return array{dossiers:int, fichiers:int, secrets:int, echecs:int}
     */
    public function reparer(): array
    {
        $bilan = ['dossiers' => 0, 'fichiers' => 0, 'secrets' => 0, 'echecs' => 0];

        foreach ($this->parcourir() as $chemin => $relatif) {
            $cible = $this->modeCible($chemin, $relatif);
            $actuel = @fileperms($chemin);
            if ($actuel !== false && ($actuel & 0777) === $cible) {
                continue;
            }
            if (!@chmod($chemin, $cible)) {
                $bilan['echecs']++;
                continue;
            }
            $cle = is_dir($chemin) ? 'dossiers' : ($cible === self::SECRET ? 'secrets' : 'fichiers');
            $bilan[$cle]++;
        }

        return $bilan;
    }

    /**
     * Aligne les droits d'une liste de chemins — utilisé après une mise à
     * jour, git ne restaurant pas les droits des fichiers récupérés.
     *
     * @param string[] $relatifs
     */
    public function normaliser(array $relatifs): int
    {
        $traites = 0;
        foreach ($relatifs as $relatif) {
            $chemin = $this->racine . '/' . ltrim($relatif, '/');
            if (!file_exists($chemin)) {
                continue;
            }
            if (is_dir($chemin)) {
                foreach ($this->parcourir($relatif) as $sous => $sousRelatif) {
                    if (@chmod($sous, $this->modeCible($sous, $sousRelatif))) {
                        $traites++;
                    }
                }
                if (@chmod($chemin, self::DOSSIER)) {
                    $traites++;
                }
            } elseif (@chmod($chemin, $this->modeCible($chemin, $relatif))) {
                $traites++;
            }
        }
        return $traites;
    }

    /**
     * Applique les droits attendus à un fichier qui vient d'être écrit.
     */
    public function fichierEcrit(string $chemin): void
    {
        $relatif = $this->relatif($chemin);
        @chmod($chemin, $this->modeCible($chemin, $relatif));
    }

    /**
     * Crée un dossier avec les bons droits, umask neutralisé.
     */
    public function creerDossier(string $chemin, bool $secret = false): bool
    {
        if (is_dir($chemin)) {
            return true;
        }
        $ancien = umask(0);
        $ok = @mkdir($chemin, self::DOSSIER, true);
        umask($ancien);
        return $ok || is_dir($chemin);
    }

    // ------------------------------------------------------------------- outils

    private function modeCible(string $chemin, string $relatif): int
    {
        if (is_dir($chemin)) {
            return self::DOSSIER;
        }
        if ($this->estSecret($relatif)) {
            return self::SECRET;
        }
        // un script déjà exécutable le reste
        $mode = @fileperms($chemin);
        if ($mode !== false && ($mode & 0100) !== 0) {
            return 0755;
        }
        return self::FICHIER;
    }

    private function estSecret(string $relatif): bool
    {
        foreach (self::SECRETS as $prefixe) {
            if ($relatif === $prefixe || str_starts_with($relatif, $prefixe . '/')) {
                return true;
            }
        }
        return false;
    }

    private function relatif(string $chemin): string
    {
        $racine = realpath($this->racine);
        $reel   = realpath($chemin) ?: $chemin;
        return $racine !== false && str_starts_with($reel, $racine . '/')
            ? substr($reel, strlen($racine) + 1)
            : $reel;
    }

    /**
     * Parcourt l'arbre en ignorant .git et consorts.
     *
     * @return \Generator<string, string> chemin absolu => chemin relatif
     */
    private function parcourir(string $depuis = ''): \Generator
    {
        $base = rtrim($this->racine . '/' . ltrim($depuis, '/'), '/');
        if (!is_dir($base)) {
            return;
        }

        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                static fn(\SplFileInfo $f) => !in_array($f->getFilename(), self::IGNORES, true)
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterateur as $fichier) {
            /** @var \SplFileInfo $fichier */
            if ($fichier->isLink()) {
                continue;
            }
            $chemin = $fichier->getPathname();
            yield $chemin => $this->relatif($chemin);
        }
    }
}
