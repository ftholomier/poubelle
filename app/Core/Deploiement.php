<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Mise à jour du site depuis le dépôt git.
 *
 * Principe de sûreté : la mise à jour ne touche QUE les chemins listés dans
 * CODE. Le contenu (data/), les photos envoyées et les sauvegardes ne sont
 * jamais dans cette liste, donc jamais écrasés — même si le dépôt contient
 * une version différente de ces fichiers.
 */
final class Deploiement
{
    /**
     * Chemins mis à jour depuis le dépôt. Tout le reste est laissé intact.
     *
     * data/.htaccess et storage/.htaccess y figurent : ce sont des fichiers
     * de sécurité, pas du contenu.
     */
    private const CODE = [
        'app',
        'config',
        'views',
        'public/index.php',
        'public/.htaccess',
        'public/assets/css',
        'public/assets/js',
        'public/assets/fonts',
        'public/assets/img/logo',
        'public/assets/img/ui',
        'data/.htaccess',
        'data/assistant/.htaccess',
        'storage/.htaccess',
        'README.md',
        'DEPLOIEMENT.md',
    ];

    /** Dossiers sauvegardés avant chaque mise à jour. */
    private const ETAT_VIVANT = ['data', 'public/assets/img/site'];

    private const DELAI = 120;

    private readonly Permissions $permissions;

    public function __construct(
        private readonly string $racine,
        private readonly Parametres $parametres,
    ) {
        $this->permissions = new Permissions($racine);
    }

    /** @return string[] */
    public static function cheminsCode(): array
    {
        return self::CODE;
    }

    // ------------------------------------------------------------ disponibilité

    /**
     * Ce que l'hébergement permet réellement.
     *
     * @return array{ok:bool, exec:bool, git:bool, depot:bool, ecriture:bool, message:string}
     */
    public function etat(): array
    {
        $exec  = $this->execAutorise();
        $git   = $exec && $this->binaireGit() !== null;
        $depot = is_dir($this->racine . '/.git');
        $ecriture = is_writable($this->racine . '/app');

        $message = match (true) {
            !$exec     => 'L’hébergement interdit l’exécution de commandes (proc_open désactivée). '
                          . 'La mise à jour automatique n’est pas possible : passez par FTP.',
            !$git      => 'La commande git est introuvable sur le serveur. '
                          . 'Contactez l’hébergeur ou passez par FTP.',
            !$depot    => 'Le site n’a pas été installé depuis git (dossier .git absent). '
                          . 'Voir la marche à suivre ci-dessous.',
            !$ecriture => 'Le dossier app/ n’est pas inscriptible : la mise à jour ne pourrait rien écrire.',
            default    => '',
        };

        return [
            'ok'       => $exec && $git && $depot && $ecriture,
            'exec'     => $exec,
            'git'      => $git,
            'depot'    => $depot,
            'ecriture' => $ecriture,
            'message'  => $message,
        ];
    }

    private function execAutorise(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }
        $desactivees = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return !in_array('proc_open', $desactivees, true);
    }

    private function binaireGit(): ?string
    {
        static $chemin = false;
        if ($chemin !== false) {
            return $chemin;
        }
        foreach (['git', '/usr/bin/git', '/usr/local/bin/git'] as $candidat) {
            [$code] = $this->executer([$candidat, '--version'], false);
            if ($code === 0) {
                return $chemin = $candidat;
            }
        }
        return $chemin = null;
    }

    // ------------------------------------------------------------------ lecture

    public function branche(): string
    {
        [$code, $sortie] = $this->git(['rev-parse', '--abbrev-ref', 'HEAD']);
        return $code === 0 ? trim($sortie) : 'inconnue';
    }

    /**
     * Adresse du dépôt, jeton d'accès retiré avant affichage.
     */
    public function depot(): string
    {
        [$code, $sortie] = $this->git(['remote', 'get-url', 'origin']);
        if ($code !== 0) {
            return '';
        }
        return preg_replace('#://[^@/]+@#', '://', trim($sortie)) ?? '';
    }

    /**
     * @return array{sha:string, date:string, sujet:string}|null
     */
    public function versionInstallee(): ?array
    {
        [$code, $sortie] = $this->git(['log', '-1', '--format=%h%x1f%cI%x1f%s']);
        if ($code !== 0 || trim($sortie) === '') {
            return null;
        }
        [$sha, $date, $sujet] = array_pad(explode("\x1f", trim($sortie)), 3, '');
        return ['sha' => $sha, 'date' => $date, 'sujet' => $sujet];
    }

    /**
     * Interroge le dépôt distant et liste ce qui n'est pas encore installé.
     *
     * @return array{retard:int, commits:array<int, array{sha:string,date:string,sujet:string}>, touche_contenu:bool}
     */
    public function verifier(): array
    {
        $branche = $this->branche();

        [$code, $sortie, $erreur] = $this->git(['fetch', '--quiet', 'origin', $branche], true);
        if ($code !== 0) {
            throw new RuntimeException($this->messageGit($erreur ?: $sortie));
        }

        $plage = 'HEAD..origin/' . $branche;

        [, $nombre] = $this->git(['rev-list', '--count', $plage]);
        [, $journal] = $this->git(['log', '--format=%h%x1f%cI%x1f%s', $plage]);

        $commits = [];
        foreach (array_filter(explode("\n", trim($journal))) as $ligne) {
            [$sha, $date, $sujet] = array_pad(explode("\x1f", $ligne), 3, '');
            $commits[] = ['sha' => $sha, 'date' => $date, 'sujet' => $sujet];
        }

        return [
            'retard'         => (int) trim($nombre),
            'commits'        => $commits,
            'touche_contenu' => $this->toucheContenu($plage),
        ];
    }

    /**
     * La mise à jour modifie-t-elle des fichiers de contenu ? Si oui, ces
     * modifications seront ignorées : l'exploitant devra les reporter à la
     * main depuis l'Éditeur avancé.
     */
    private function toucheContenu(string $plage): bool
    {
        [, $sortie] = $this->git(['diff', '--name-only', $plage, '--', 'data']);
        foreach (array_filter(explode("\n", trim($sortie))) as $fichier) {
            if (!in_array(trim($fichier), self::CODE, true)) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------- application

    /**
     * Applique la mise à jour. Renvoie le compte rendu ligne par ligne.
     *
     * @return string[]
     */
    public function appliquer(): array
    {
        $etat = $this->etat();
        if (!$etat['ok']) {
            throw new RuntimeException($etat['message']);
        }

        $branche = $this->branche();
        $cible   = 'origin/' . $branche;
        $journal = [];

        [$code, $sortie, $erreur] = $this->git(['fetch', '--quiet', 'origin', $branche], true);
        if ($code !== 0) {
            throw new RuntimeException($this->messageGit($erreur ?: $sortie));
        }
        $journal[] = 'Dépôt distant interrogé (' . $cible . ').';

        $archive = $this->sauvegarder();
        $journal[] = 'Sauvegarde du contenu et des photos : ' . basename($archive);

        // fichiers de code supprimés dans la nouvelle version
        [, $diff] = $this->git(array_merge(
            ['diff', '--name-status', 'HEAD', $cible, '--'],
            self::CODE
        ));
        $supprimes = 0;
        foreach (array_filter(explode("\n", trim($diff))) as $ligne) {
            if (!str_starts_with($ligne, 'D')) {
                continue;
            }
            $fichier = trim(substr($ligne, 1));
            if ($fichier !== '' && is_file($this->racine . '/' . $fichier)) {
                @unlink($this->racine . '/' . $fichier);
                $supprimes++;
            }
        }
        if ($supprimes > 0) {
            $journal[] = $supprimes . ' fichier(s) devenu(s) inutile(s) retiré(s).';
        }

        // seuls les chemins présents dans la nouvelle version sont récupérés
        $presents = array_values(array_filter(
            self::CODE,
            fn(string $c) => $this->git(['cat-file', '-e', $cible . ':' . $c])[0] === 0
        ));
        if ($presents === []) {
            throw new RuntimeException('Aucun chemin de code trouvé dans la version distante.');
        }

        [$code, $sortie, $erreur] = $this->git(
            array_merge(['checkout', $cible, '--'], $presents),
            true
        );
        if ($code !== 0) {
            throw new RuntimeException('Récupération du code impossible : ' . trim($erreur ?: $sortie));
        }
        $journal[] = count($presents) . ' chemin(s) de code mis à jour.';

        // git ne restaure pas les droits : on les réaligne sur les chemins reçus
        $ajustes = $this->permissions->normaliser($presents);
        if ($ajustes > 0) {
            $journal[] = $ajustes . ' droit(s) d’accès réalignés.';
        }

        // HEAD avance ; le contenu local reste tel quel et apparaîtra
        // simplement comme « modifié » vis-à-vis du dépôt, ce qui est normal
        [$code, $sortie, $erreur] = $this->git(['reset', '--mixed', '--quiet', $cible], true);
        if ($code !== 0) {
            $journal[] = 'Avertissement : la référence locale n’a pas pu avancer ('
                . trim($erreur ?: $sortie) . ').';
        } else {
            $journal[] = 'Version installée mise à jour.';
        }

        $this->purgerSauvegardes();

        return $journal;
    }

    // ------------------------------------------------------------ sauvegardes

    public function dossierSauvegardes(): string
    {
        return $this->racine . '/storage/deploiements';
    }

    /**
     * Archive le contenu et les photos avant toute intervention.
     */
    public function sauvegarder(): string
    {
        $dossier = $this->dossierSauvegardes();
        if (!$this->permissions->creerDossier($dossier)) {
            throw new RuntimeException('Impossible de créer ' . $dossier);
        }

        $archive = $dossier . '/contenu-' . date('Ymd-His') . '.tar.gz';
        $cibles = array_values(array_filter(
            self::ETAT_VIVANT,
            fn(string $c) => is_dir($this->racine . '/' . $c)
        ));

        [$code, $sortie, $erreur] = $this->executer(
            array_merge(['tar', '-czf', $archive, '-C', $this->racine], $cibles),
            true
        );
        if ($code !== 0 || !is_file($archive)) {
            throw new RuntimeException('Sauvegarde impossible : ' . trim($erreur ?: $sortie));
        }
        // l'archive embarque data/admin : mot de passe SMTP et empreinte du
        // compte. Elle ne doit jamais être lisible par d'autres comptes.
        @chmod($archive, Permissions::SECRET);

        return $archive;
    }

    /**
     * @return array<int, array{fichier:string, nom:string, date:string, taille:int}>
     */
    public function sauvegardes(): array
    {
        $liste = glob($this->dossierSauvegardes() . '/contenu-*.tar.gz') ?: [];
        rsort($liste);

        return array_map(function (string $f): array {
            preg_match('/-(\d{8})-(\d{6})\.tar\.gz$/', $f, $m);
            $date = $m
                ? substr($m[1], 6, 2) . '/' . substr($m[1], 4, 2) . '/' . substr($m[1], 0, 4)
                  . ' à ' . substr($m[2], 0, 2) . ':' . substr($m[2], 2, 2)
                : basename($f);
            return ['fichier' => $f, 'nom' => basename($f), 'date' => $date, 'taille' => (int) filesize($f)];
        }, $liste);
    }

    /**
     * Restaure une archive de contenu (le contenu courant est d'abord archivé).
     */
    public function restaurer(string $archive): void
    {
        $reel = realpath($archive);
        $base = realpath($this->dossierSauvegardes());
        if ($reel === false || $base === false || !str_starts_with($reel, $base . '/')) {
            throw new RuntimeException('Archive invalide.');
        }

        $this->sauvegarder();

        [$code, $sortie, $erreur] = $this->executer(
            ['tar', '-xzf', $reel, '-C', $this->racine],
            true
        );
        if ($code !== 0) {
            throw new RuntimeException('Restauration impossible : ' . trim($erreur ?: $sortie));
        }
        $this->permissions->normaliser(self::ETAT_VIVANT);
    }

    /** Ne conserve que les 10 archives les plus récentes. */
    private function purgerSauvegardes(): void
    {
        foreach (array_slice($this->sauvegardes(), 10) as $vieille) {
            @unlink($vieille['fichier']);
        }
    }

    // ---------------------------------------------------------------- commandes

    /**
     * @param string[] $arguments
     * @return array{0:int, 1:string, 2:string}
     */
    private function git(array $arguments, bool $avecErreur = false): array
    {
        $binaire = $this->binaireGit();
        if ($binaire === null) {
            return [127, '', 'git introuvable'];
        }
        return $this->executer(array_merge([$binaire], $arguments), $avecErreur);
    }

    /**
     * Exécute une commande sans passer par un shell : les arguments ne sont
     * jamais réinterprétés.
     *
     * @param string[] $commande
     * @return array{0:int, 1:string, 2:string}
     */
    private function executer(array $commande, bool $avecErreur = false): array
    {
        if (!$this->execAutorise()) {
            return [126, '', 'exécution interdite'];
        }

        $descripteurs = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $processus = @proc_open(
            $commande,
            $descripteurs,
            $tuyaux,
            $this->racine,
            [
                'HOME'         => $this->racine,
                'GIT_TERMINAL_PROMPT' => '0',
                'GIT_ASKPASS'  => 'echo',
                'LC_ALL'       => 'C',
                'PATH'         => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            ]
        );
        if (!is_resource($processus)) {
            return [1, '', 'lancement impossible'];
        }

        stream_set_blocking($tuyaux[1], false);
        stream_set_blocking($tuyaux[2], false);

        $sortie = '';
        $erreur = '';
        $limite = microtime(true) + self::DELAI;

        while (true) {
            $sortie .= stream_get_contents($tuyaux[1]);
            $erreur .= stream_get_contents($tuyaux[2]);

            $infos = proc_get_status($processus);
            if (!$infos['running']) {
                break;
            }
            if (microtime(true) > $limite) {
                proc_terminate($processus, 9);
                $erreur .= "\nCommande interrompue après " . self::DELAI . ' secondes.';
                break;
            }
            usleep(50_000);
        }

        $sortie .= stream_get_contents($tuyaux[1]);
        $erreur .= stream_get_contents($tuyaux[2]);
        fclose($tuyaux[1]);
        fclose($tuyaux[2]);

        $code = proc_close($processus);
        if (isset($infos) && !$infos['running'] && $infos['exitcode'] >= 0) {
            $code = $infos['exitcode'];
        }

        return [$code, $sortie, $avecErreur ? $erreur : ''];
    }

    /**
     * Traduit les échecs git courants en conseil actionnable.
     */
    private function messageGit(string $brut): string
    {
        $brut = trim(preg_replace('#://[^@/]+@#', '://', $brut) ?? $brut);

        return match (true) {
            str_contains($brut, 'Authentication failed'),
            str_contains($brut, 'could not read Username'),
            str_contains($brut, 'Invalid username or password')
                => 'Le dépôt a refusé l’authentification. Si le dépôt est privé, '
                   . 'installez une clé SSH de déploiement ou utilisez une adresse '
                   . 'contenant un jeton d’accès.',
            str_contains($brut, 'Permission denied (publickey)')
                => 'La clé SSH du serveur n’est pas acceptée par le dépôt. '
                   . 'Ajoutez la clé publique du serveur comme clé de déploiement.',
            str_contains($brut, 'Could not resolve host'),
            str_contains($brut, 'unable to access')
                => 'Le serveur n’a pas pu joindre le dépôt (réseau ou pare-feu).',
            $brut === '' => 'Échec de la connexion au dépôt, sans message d’erreur.',
            default => 'Échec de la connexion au dépôt : ' . $brut,
        };
    }
}
