<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Langues du site.
 *
 * Le français est la langue source : c'est lui qu'on édite, et les autres
 * langues n'en sont que des traductions stockées à côté. Ajouter une langue
 * revient à ajouter une entrée ici — aucune route ni aucun gabarit à écrire.
 */
final class Langues
{
    /** Langue source, servie sans préfixe d'adresse. */
    public const SOURCE = 'fr';

    /**
     * Langues proposées à l'ajout. La liste sert d'aide à la saisie ; une
     * langue absente peut toujours être ajoutée par son code.
     */
    public const CONNUES = [
        'en' => ['nom' => 'English',    'nom_fr' => 'Anglais'],
        'de' => ['nom' => 'Deutsch',    'nom_fr' => 'Allemand'],
        'nl' => ['nom' => 'Nederlands', 'nom_fr' => 'Néerlandais'],
        'es' => ['nom' => 'Español',    'nom_fr' => 'Espagnol'],
        'it' => ['nom' => 'Italiano',   'nom_fr' => 'Italien'],
        'pt' => ['nom' => 'Português',  'nom_fr' => 'Portugais'],
        'pl' => ['nom' => 'Polski',     'nom_fr' => 'Polonais'],
    ];

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(private readonly string $fichier)
    {
    }

    /**
     * Toutes les langues, français compris, dans l'ordre d'affichage.
     *
     * @return array<string, array{code: string, nom: string, actif: bool, prefixe: string}>
     */
    public function toutes(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $brut = [];
        if (is_file($this->fichier)) {
            $lu = json_decode((string) file_get_contents($this->fichier), true);
            if (is_array($lu)) {
                $brut = $lu['langues'] ?? [];
            }
        }

        // le français est toujours là, en tête, et ne se désactive pas
        $langues = [self::SOURCE => [
            'code'    => self::SOURCE,
            'nom'     => 'Français',
            'actif'   => true,
            'prefixe' => '',
        ]];

        foreach ($brut as $entree) {
            $code = self::normaliserCode((string) ($entree['code'] ?? ''));
            if ($code === '' || $code === self::SOURCE) {
                continue;
            }
            $langues[$code] = [
                'code'    => $code,
                'nom'     => trim((string) ($entree['nom'] ?? '')) ?: ($code === '' ? $code : strtoupper($code)),
                'actif'   => (bool) ($entree['actif'] ?? true),
                'prefixe' => $code,
            ];
        }

        return $this->cache = $langues;
    }

    /**
     * Langues réellement servies aux visiteurs.
     *
     * @return array<string, array{code: string, nom: string, actif: bool, prefixe: string}>
     */
    public function publiees(): array
    {
        return array_filter($this->toutes(), static fn(array $l): bool => $l['actif']);
    }

    public function existe(string $code): bool
    {
        return isset($this->toutes()[$code]);
    }

    public function estPubliee(string $code): bool
    {
        return (bool) ($this->toutes()[$code]['actif'] ?? false);
    }

    public function nom(string $code): string
    {
        return (string) ($this->toutes()[$code]['nom'] ?? strtoupper($code));
    }

    /**
     * Code ISO d'une saisie libre : deux ou trois lettres, en minuscules.
     */
    public static function normaliserCode(string $code): string
    {
        $code = strtolower(trim($code));
        return preg_match('/^[a-z]{2,3}$/', $code) === 1 ? $code : '';
    }

    /**
     * Langue déduite d'un chemin, avec le chemin débarrassé de son préfixe.
     *
     * /en/hebergements donne ['en', '/hebergements'] ; une adresse sans
     * préfixe connu reste intacte et relève du français.
     *
     * @return array{0: string, 1: string}
     */
    public function detecter(string $chemin): array
    {
        $chemin = '/' . ltrim($chemin, '/');
        $premier = strtolower(explode('/', trim($chemin, '/'))[0] ?? '');

        if ($premier !== '' && $premier !== self::SOURCE && $this->estPubliee($premier)) {
            $reste = substr($chemin, strlen($premier) + 1);
            return [$premier, $reste === '' ? '/' : $reste];
        }

        return [self::SOURCE, $chemin];
    }

    /**
     * Préfixe d'adresse d'une langue : '' pour le français, '/en' sinon.
     */
    public function prefixe(string $code): string
    {
        return $code === self::SOURCE || !$this->existe($code) ? '' : '/' . $code;
    }

    // -------------------------------------------------------------- écriture

    public function ajouter(string $code, string $nom): string
    {
        $code = self::normaliserCode($code);
        if ($code === '') {
            throw new RuntimeException('Code de langue invalide : deux lettres attendues, comme « en ».');
        }
        if ($code === self::SOURCE) {
            throw new RuntimeException('Le français est la langue du site : il est déjà là.');
        }
        if ($this->existe($code)) {
            throw new RuntimeException('La langue « ' . $code . ' » existe déjà.');
        }

        $nom = trim($nom) ?: (self::CONNUES[$code]['nom'] ?? strtoupper($code));

        $langues = $this->sansSource();
        $langues[] = ['code' => $code, 'nom' => $nom, 'actif' => false];
        $this->enregistrer($langues);

        return $nom;
    }

    /**
     * Met une langue en ligne ou hors ligne, et rend son nouvel état.
     */
    public function basculer(string $code): bool
    {
        if ($code === self::SOURCE) {
            throw new RuntimeException('Le français ne peut pas être retiré du site.');
        }

        $langues = $this->sansSource();
        $etat = false;
        foreach ($langues as $rang => $l) {
            if ($l['code'] === $code) {
                $etat = !($l['actif'] ?? true);
                $langues[$rang]['actif'] = $etat;
            }
        }
        $this->enregistrer($langues);

        return $etat;
    }

    public function supprimer(string $code): void
    {
        if ($code === self::SOURCE) {
            throw new RuntimeException('Le français ne peut pas être supprimé.');
        }

        $this->enregistrer(array_values(array_filter(
            $this->sansSource(),
            static fn(array $l): bool => $l['code'] !== $code
        )));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sansSource(): array
    {
        $langues = [];
        foreach ($this->toutes() as $code => $l) {
            if ($code !== self::SOURCE) {
                $langues[] = ['code' => $code, 'nom' => $l['nom'], 'actif' => $l['actif']];
            }
        }
        return $langues;
    }

    /**
     * @param array<int, array<string, mixed>> $langues
     */
    private function enregistrer(array $langues): void
    {
        $dossier = dirname($this->fichier);
        if (!is_dir($dossier)) {
            $ancien = umask(0);
            @mkdir($dossier, Permissions::DOSSIER, true);
            umask($ancien);
        }

        $json = json_encode(
            ['langues' => array_values($langues)],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $tmp = $this->fichier . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, $this->fichier)) {
            @unlink($tmp);
            throw new RuntimeException('Écriture impossible : langues.json');
        }
        @chmod($this->fichier, Permissions::FICHIER);

        $this->cache = null;
    }
}
