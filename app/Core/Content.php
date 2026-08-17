<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Accès au contenu stocké en JSON dans /data (hors racine web).
 *
 * C'est la seule porte d'entrée vers le contenu : le futur back-office
 * écrira dans ces mêmes fichiers via save(), sans toucher aux gabarits.
 */
final class Content
{
    /** @var array<string, array<mixed>> */
    private array $cache = [];

    public function __construct(private readonly string $dir)
    {
    }

    /**
     * Charge data/<name>.json.
     *
     * @return array<mixed>
     */
    public function load(string $name): array
    {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $file = $this->path($name);
        if (!is_file($file)) {
            throw new RuntimeException("Contenu introuvable : {$name}.json");
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new RuntimeException("Lecture impossible : {$name}.json");
        }

        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException("JSON invalide : {$name}.json");
        }

        return $this->cache[$name] = $data;
    }

    /**
     * Valeur unique via un chemin pointé : get('site', 'contact.telephone').
     */
    public function get(string $name, string $path, mixed $default = null): mixed
    {
        $value = $this->load($name);
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /**
     * Retrouve une entrée par son slug dans une collection.
     *
     * @return array<mixed>|null
     */
    public function find(string $name, string $slug, string $collection = 'items'): ?array
    {
        foreach ($this->load($name)[$collection] ?? [] as $item) {
            if (is_array($item) && ($item['slug'] ?? null) === $slug) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Une entrée est-elle visible du public ?
     *
     * Les contenus antérieurs à la mise en place du réglage n'ont pas la clé
     * et restent donc visibles.
     *
     * @param array<mixed> $item
     */
    public static function estPublie(array $item): bool
    {
        return (bool) ($item['actif'] ?? true);
    }

    /**
     * Entrées d'une collection visibles du public.
     *
     * @return array<int, array<mixed>>
     */
    public function publies(string $name, string $collection = 'items'): array
    {
        return array_values(array_filter(
            $this->load($name)[$collection] ?? [],
            fn($item) => is_array($item) && self::estPublie($item)
        ));
    }

    /**
     * Menu du site, dont les sous-menus de collection sont reconstruits à
     * partir des données.
     *
     * Sans cela, ajouter un hébergement dans le back-office le ferait
     * apparaître sur la page liste mais pas dans la navigation, qui resterait
     * figée sur ce qu'un ancien enregistrement de site.json contient.
     *
     * Les entrées de sous-menu qui ne viennent pas de la collection — le
     * règlement général sous Pêche, par exemple — sont conservées telles
     * quelles, à leur place.
     *
     * @return array<mixed>
     */
    public function menu(): array
    {
        $menu = $this->load('site')['menu'] ?? [];

        foreach ($menu as $i => $entree) {
            $base = rtrim((string) ($entree['url'] ?? ''), '/');
            $collection = match ($base) {
                '/hebergements' => 'hebergements',
                '/peche'        => 'peche',
                default         => null,
            };
            if ($collection === null || !isset($entree['sous_menu'])) {
                continue;
            }

            $liens = [];
            foreach ($this->publies($collection) as $item) {
                if (($item['slug'] ?? '') === '') {
                    continue;
                }
                $liens[] = [
                    'libelle' => $item['nom'] ?? $item['slug'],
                    'url'     => $base . '/' . $item['slug'],
                ];
            }

            // les liens ajoutés à la main restent après ceux de la collection
            foreach ($entree['sous_menu'] as $lien) {
                if (!str_starts_with(rtrim((string) ($lien['url'] ?? ''), '/'), $base . '/')) {
                    $liens[] = $lien;
                }
            }

            $menu[$i]['sous_menu'] = $liens;
        }

        return $menu;
    }

    /**
     * Écriture atomique avec sauvegarde de la version précédente.
     *
     * @param array<mixed> $data
     */
    public function save(string $name, array $data): void
    {
        $file = $this->path($name);
        $tmp  = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        if (is_file($file)) {
            $this->sauvegarder($name, $file);
        }

        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException("Écriture impossible : {$name}.json");
        }
        // le umask du serveur est imprévisible : on fixe les droits
        @chmod($file, Permissions::FICHIER);

        unset($this->cache[$name]);
    }

    /**
     * Sauvegardes disponibles pour un contenu, les plus récentes d'abord.
     *
     * @return string[] chemins absolus
     */
    public function sauvegardes(string $name): array
    {
        $motif = $this->dossierSauvegardes() . '/' . str_replace('/', '__', $name) . '-*.json';
        $liste = glob($motif) ?: [];
        rsort($liste);
        return $liste;
    }

    /**
     * Restaure une sauvegarde (le contenu courant est lui-même sauvegardé).
     */
    public function restaurer(string $name, string $fichierSauvegarde): void
    {
        $reel = realpath($fichierSauvegarde);
        $base = realpath($this->dossierSauvegardes());
        if ($reel === false || $base === false || !str_starts_with($reel, $base . '/')) {
            throw new RuntimeException('Sauvegarde invalide.');
        }
        $donnees = json_decode((string) file_get_contents($reel), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($donnees)) {
            throw new RuntimeException('Sauvegarde illisible.');
        }
        $this->save($name, $donnees);
    }

    private function sauvegarder(string $name, string $file): void
    {
        $dossier = $this->dossierSauvegardes();
        if (!is_dir($dossier)) {
            $ancien = umask(0);
            @mkdir($dossier, Permissions::DOSSIER, true);
            umask($ancien);
        }
        $slug = str_replace('/', '__', $name);
        $copie = $dossier . '/' . $slug . '-' . date('Ymd-His') . '.json';
        if (@copy($file, $copie)) {
            @chmod($copie, Permissions::FICHIER);
        }

        // ne conserver que les 20 plus récentes par contenu
        $liste = glob($dossier . '/' . $slug . '-*.json') ?: [];
        rsort($liste);
        foreach (array_slice($liste, 20) as $ancienne) {
            @unlink($ancienne);
        }
    }

    private function dossierSauvegardes(): string
    {
        return dirname($this->dir) . '/storage/sauvegardes';
    }

    private function path(string $name): string
    {
        if (!preg_match('#^[a-z0-9][a-z0-9/_-]*$#', $name) || str_contains($name, '..')) {
            throw new RuntimeException("Nom de contenu invalide : {$name}");
        }
        return $this->dir . '/' . $name . '.json';
    }
}
