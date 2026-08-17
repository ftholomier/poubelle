<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Adresses et référencement.
 *
 * Une seule source décrit, pour chaque page, son adresse (slug) et ses
 * métadonnées. Les routes en sont déduites, les gabarits y lisent leurs
 * balises, et le plan du site s'en construit — changer un slug ne demande
 * donc de toucher à rien d'autre.
 */
final class Seo
{
    /**
     * Pages fixes du site : clé interne => slug livré et libellé d'écran.
     * Le slug reste modifiable, cette table ne sert que d'état initial.
     */
    public const PAGES = [
        'accueil'          => ['slug' => '',                  'nom' => 'Accueil'],
        'hebergements'     => ['slug' => 'hebergements',      'nom' => 'Hébergements'],
        'peche'            => ['slug' => 'peche',             'nom' => 'Pêche'],
        'boutique'         => ['slug' => 'boutique',          'nom' => 'Boutique'],
        'galerie'          => ['slug' => 'galerie',           'nom' => 'Galerie'],
        'contact'          => ['slug' => 'contact',           'nom' => 'Contact'],
        'reglement'        => ['slug' => 'reglement-general', 'nom' => 'Règlement général'],
        'mentions-legales' => ['slug' => 'mentions-legales',  'nom' => 'Mentions légales'],
    ];

    /** Pages dont le slug préfixe celui des fiches. */
    public const COLLECTIONS = ['hebergements' => 'hebergements', 'peche' => 'peche'];

    /** Slugs qu'on ne peut pas prendre : ils appartiennent à l'application. */
    private const RESERVES = ['admin', 'api', 'assets', 'sitemap.xml', 'robots.txt'];

    /** Fichiers de contenu susceptibles de contenir des liens internes. */
    private const CONTENUS = [
        'site', 'galerie', 'hebergements', 'peche',
        'pages/accueil', 'pages/boutique', 'pages/contact', 'pages/galerie',
        'pages/hebergements', 'pages/peche', 'pages/reglement', 'pages/mentions-legales',
    ];

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly string $fichier,
        private readonly Content $content,
    ) {
    }

    // ---------------------------------------------------------------- lecture

    /**
     * @return array<string, mixed>
     */
    public function tout(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $brut = [];
        if (is_file($this->fichier)) {
            $lu = json_decode((string) file_get_contents($this->fichier), true);
            if (is_array($lu)) {
                $brut = $lu;
            }
        }

        $pages = [];
        foreach (self::PAGES as $cle => $defaut) {
            $enregistre = $brut['pages'][$cle] ?? [];
            $pages[$cle] = [
                'nom'         => $defaut['nom'],
                'slug'        => (string) ($enregistre['slug'] ?? $defaut['slug']),
                'titre'       => (string) ($enregistre['titre'] ?? ''),
                'description' => (string) ($enregistre['description'] ?? ''),
                'image'       => (string) ($enregistre['image'] ?? ''),
                'indexer'     => (bool) ($enregistre['indexer'] ?? true),
            ];
        }

        return $this->cache = [
            'pages'         => $pages,
            'redirections'  => is_array($brut['redirections'] ?? null) ? $brut['redirections'] : [],
            'image_partage' => (string) ($brut['image_partage'] ?? ''),
            'indexer_site'  => (bool) ($brut['indexer_site'] ?? true),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function pages(): array
    {
        return $this->tout()['pages'];
    }

    /**
     * @return array<string, mixed>
     */
    public function page(string $cle): array
    {
        return $this->pages()[$cle] ?? ['nom' => $cle, 'slug' => $cle, 'titre' => '',
                                        'description' => '', 'image' => '', 'indexer' => true];
    }

    public function slug(string $cle): string
    {
        return $this->page($cle)['slug'];
    }

    /**
     * Chemin d'une page, éventuellement d'une fiche de collection.
     */
    public function chemin(string $cle, ?string $item = null): string
    {
        $slug = $this->slug($cle);
        $base = $slug === '' ? '/' : '/' . $slug;
        return $item !== null ? rtrim($base, '/') . '/' . $item : $base;
    }

    /**
     * @return array<string, string>
     */
    public function redirections(): array
    {
        return $this->tout()['redirections'];
    }

    /**
     * Adresses de base des collections, pour reconstruire les sous-menus
     * même après un changement de slug.
     *
     * @return array<string, string> chemin de base => collection
     */
    public function basesCollections(): array
    {
        $bases = [];
        foreach (self::COLLECTIONS as $cle => $collection) {
            $bases[rtrim($this->chemin($cle), '/')] = $collection;
        }
        return $bases;
    }

    public function indexable(string $cle): bool
    {
        return $this->tout()['indexer_site'] && $this->page($cle)['indexer'];
    }

    /**
     * Titre et description réellement publiés, avec leur origine.
     *
     * Le réglage saisi dans l'écran Référencement prime ; à défaut on retombe
     * sur le titre et la description de la page éditoriale, puis sur
     * l'accroche du site — une balise vide n'existe jamais.
     *
     * @param array<string, mixed>|null $page contenu de la page, ou d'une fiche
     * @param bool $fiche true si $page décrit une fiche : ses métadonnées
     *                    priment alors sur celles de la page de collection,
     *                    qui ne décrivent que la liste
     * @return array{titre: string, description: string, titre_herite: bool, description_heritee: bool}
     */
    public function meta(string $cle, ?array $page = null, bool $fiche = false): array
    {
        $reglages = $this->page($cle);
        $site     = $this->content->load('site');
        $page   ??= $this->contenuPage($cle);

        // L'accueil porte le titre long du site ; ailleurs, le titre de la
        // page suivi du seul nom du domaine — répéter la phrase complète
        // dépasserait la longueur affichée par Google sur chaque page.
        $titrePage = trim((string) ($page['titre'] ?? ''));
        $titreLong = ($titrePage !== '' && $cle !== 'accueil')
            ? $titrePage . ' - ' . (string) $site['nom']
            : (string) $site['titre_seo'];

        $descPage = trim((string) ($page['meta']['description'] ?? ''));
        $descSite = (string) $site['accroche'];

        // sur une fiche, le réglage de la page de collection ne s'applique pas
        $titreReglage = $fiche ? '' : $reglages['titre'];
        $descReglage  = $fiche ? '' : $reglages['description'];

        return [
            'titre'               => $titreReglage !== '' ? $titreReglage : $titreLong,
            'description'         => $descReglage !== '' ? $descReglage
                                     : ($descPage !== '' ? $descPage : $descSite),
            'titre_herite'        => $titreReglage === '',
            'description_heritee' => $descReglage === '',
        ];
    }

    /**
     * Contenu éditorial d'une page fixe, tolérant à un fichier absent.
     *
     * @return array<string, mixed>
     */
    private function contenuPage(string $cle): array
    {
        try {
            return $this->content->load('pages/' . $cle);
        } catch (RuntimeException) {
            return [];
        }
    }

    /**
     * Photo utilisée par les réseaux sociaux et les messageries : celle de la
     * page si elle en a une, sinon celle du site, sinon la grande photo
     * d'accueil — mieux vaut cette dernière qu'un partage sans visuel.
     */
    public function imagePartage(string $cle = ''): string
    {
        $propre = $cle !== '' ? $this->page($cle)['image'] : '';
        if ($propre !== '') {
            return $propre;
        }

        $site = $this->tout()['image_partage'];
        if ($site !== '') {
            return $site;
        }

        return (string) $this->content->get('pages/accueil', 'hero.image', '');
    }

    // -------------------------------------------------------------- écriture

    /**
     * @param array<string, mixed> $donnees
     */
    private function enregistrer(array $donnees): void
    {
        $dossier = dirname($this->fichier);
        if (!is_dir($dossier)) {
            $ancien = umask(0);
            @mkdir($dossier, Permissions::DOSSIER, true);
            umask($ancien);
        }

        $json = json_encode(
            $donnees,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $tmp = $this->fichier . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, $this->fichier)) {
            @unlink($tmp);
            throw new RuntimeException('Écriture impossible : seo.json');
        }
        @chmod($this->fichier, Permissions::FICHIER);

        $this->cache = null;
    }

    /**
     * Normalise une saisie libre en slug d'URL.
     */
    public static function normaliser(string $valeur): string
    {
        $valeur = trim($valeur, " \t\n\r\0\x0B/");
        if (function_exists('iconv')) {
            $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $valeur);
            if ($translit !== false) {
                $valeur = $translit;
            }
        }
        $valeur = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $valeur) ?? '');
        return trim(preg_replace('/-+/', '-', $valeur) ?? '', '-');
    }

    /**
     * Met à jour une page : slug, métadonnées, indexation.
     *
     * Un changement de slug enregistre une redirection permanente depuis
     * l'ancienne adresse et réécrit les liens du menu, pour qu'aucun lien
     * existant ne tombe dans le vide.
     *
     * @param array<string, mixed> $valeurs
     * @return string message décrivant ce qui a changé
     */
    public function majPage(string $cle, array $valeurs): string
    {
        if (!isset(self::PAGES[$cle])) {
            throw new RuntimeException('Page inconnue : ' . $cle);
        }

        $tout = $this->tout();
        $avant = $tout['pages'][$cle]['slug'];
        $apres = $avant;

        // l'accueil est servi à la racine : son slug n'est pas modifiable
        if ($cle !== 'accueil' && array_key_exists('slug', $valeurs)) {
            $apres = self::normaliser((string) $valeurs['slug']);
            if ($apres === '') {
                throw new RuntimeException('Le slug ne peut pas être vide.');
            }
            if (in_array($apres, self::RESERVES, true)) {
                throw new RuntimeException('« ' . $apres . ' » est réservé par l’application.');
            }
            foreach ($tout['pages'] as $autre => $p) {
                if ($autre !== $cle && $p['slug'] === $apres) {
                    throw new RuntimeException('« ' . $apres . ' » est déjà l’adresse de : ' . $p['nom'] . '.');
                }
            }
        }

        $tout['pages'][$cle]['slug']        = $apres;
        $tout['pages'][$cle]['titre']       = trim((string) ($valeurs['titre'] ?? ''));
        $tout['pages'][$cle]['description'] = trim((string) ($valeurs['description'] ?? ''));
        $tout['pages'][$cle]['image']       = trim((string) ($valeurs['image'] ?? ''));
        $tout['pages'][$cle]['indexer']     = (bool) ($valeurs['indexer'] ?? false);

        $message = 'Page « ' . $tout['pages'][$cle]['nom'] . ' » enregistrée.';

        if ($apres !== $avant) {
            $ancienChemin = '/' . $avant;
            $tout['redirections'][$ancienChemin] = '/' . $apres;
            // une redirection vers l'ancienne adresse n'a plus lieu d'être
            unset($tout['redirections']['/' . $apres]);

            $this->enregistrer($tout);
            $this->reecrireLiens($ancienChemin, '/' . $apres);

            return $message . ' L’ancienne adresse ' . $ancienChemin
                . ' redirige désormais vers /' . $apres . '.';
        }

        $this->enregistrer($tout);
        return $message;
    }

    /**
     * Met à jour l'adresse et la description d'une fiche de collection.
     *
     * @param array<string, mixed> $valeurs
     * @return string message décrivant ce qui a changé
     */
    public function majFiche(string $cle, string $slug, array $valeurs): string
    {
        $collection = self::COLLECTIONS[$cle] ?? null;
        if ($collection === null) {
            throw new RuntimeException('Collection inconnue : ' . $cle);
        }

        $donnees = $this->content->load($collection);
        $rang = null;
        foreach ($donnees['items'] as $i => $item) {
            if (($item['slug'] ?? '') === $slug) {
                $rang = $i;
                break;
            }
        }
        if ($rang === null) {
            throw new RuntimeException('Fiche introuvable : ' . $slug);
        }

        $apres = self::normaliser((string) ($valeurs['slug'] ?? $slug));
        if ($apres === '') {
            throw new RuntimeException('L’adresse ne peut pas être vide.');
        }
        foreach ($donnees['items'] as $i => $item) {
            if ($i !== $rang && ($item['slug'] ?? '') === $apres) {
                throw new RuntimeException('« ' . $apres . ' » est déjà l’adresse de : '
                    . ($item['nom'] ?? $apres) . '.');
            }
        }

        $nom = (string) ($donnees['items'][$rang]['nom'] ?? $slug);
        $donnees['items'][$rang]['slug'] = $apres;
        if (array_key_exists('description', $valeurs)) {
            $donnees['items'][$rang]['meta']['description'] = trim((string) $valeurs['description']);
        }
        $this->content->save($collection, $donnees);

        $message = '« ' . $nom .' » enregistré.';
        if ($apres !== $slug) {
            $ancien = $this->chemin($cle, $slug);
            $this->noterRedirection($ancien, $this->chemin($cle, $apres));
            $message .= ' L’ancienne adresse ' . $ancien . ' redirige désormais vers '
                . $this->chemin($cle, $apres) . '.';
        }

        return $message;
    }

    /**
     * Ajoute une redirection permanente après le changement de slug d'une
     * fiche de collection.
     */
    public function noterRedirection(string $ancien, string $nouveau): void
    {
        $tout = $this->tout();
        $tout['redirections'][$ancien] = $nouveau;
        unset($tout['redirections'][$nouveau]);
        $this->enregistrer($tout);
        $this->reecrireLiens($ancien, $nouveau);
    }

    /**
     * Redirection saisie à la main, pour rattraper une adresse qui circule
     * ailleurs (ancien site, flyer, annuaire).
     */
    public function ajouterRedirection(string $depuis, string $vers): void
    {
        $depuis = '/' . trim($depuis, " \t/");
        $vers   = '/' . trim($vers, " \t/");

        if ($depuis === '/') {
            throw new RuntimeException('L’adresse de départ ne peut pas être la racine du site.');
        }
        if ($depuis === $vers) {
            throw new RuntimeException('Une adresse ne peut pas rediriger vers elle-même.');
        }

        $tout = $this->tout();
        $tout['redirections'][$depuis] = $vers;
        $this->enregistrer($tout);
    }

    public function retirerRedirection(string $depuis): void
    {
        $tout = $this->tout();
        unset($tout['redirections'][$depuis]);
        $this->enregistrer($tout);
    }

    /**
     * @param array<string, mixed> $valeurs
     */
    public function majGeneral(array $valeurs): void
    {
        $tout = $this->tout();
        $tout['image_partage'] = trim((string) ($valeurs['image_partage'] ?? ''));
        $tout['indexer_site']  = (bool) ($valeurs['indexer_site'] ?? false);
        $this->enregistrer($tout);
    }

    /**
     * Adresse de destination d'une ancienne adresse, sous-pages comprises.
     *
     * Renommer /hebergements doit aussi faire suivre /hebergements/le-gite :
     * la table ne retient que le préfixe, la partie restante est recollée.
     * Les redirections en chaîne (A→B puis B→C) sont suivies jusqu'au bout.
     */
    public function cible(string $chemin): ?string
    {
        $chemin = rtrim($chemin, '/') ?: '/';
        $table  = $this->redirections();
        $depart = $chemin;

        // 5 sauts suffisent largement, et bornent une éventuelle boucle
        for ($saut = 0; $saut < 5; $saut++) {
            $trouve = null;

            if (isset($table[$chemin])) {
                $trouve = $table[$chemin];
            } else {
                foreach ($table as $ancien => $nouveau) {
                    if ($ancien !== '/' && str_starts_with($chemin, $ancien . '/')) {
                        $trouve = $nouveau . substr($chemin, strlen($ancien));
                        break;
                    }
                }
            }

            if ($trouve === null || $trouve === $chemin) {
                break;
            }
            $chemin = $trouve;
        }

        return $chemin !== $depart ? $chemin : null;
    }

    /**
     * Fait suivre les liens enregistrés dans le contenu quand une adresse
     * change : menu, sous-menus, boutons des blocs d'accueil.
     *
     * Seules les valeurs de clés « url » sont touchées — jamais un texte
     * rédactionnel qui contiendrait par hasard l'ancienne adresse.
     */
    private function reecrireLiens(string $ancien, string $nouveau): void
    {
        foreach (self::CONTENUS as $nom) {
            try {
                $donnees = $this->content->load($nom);
            } catch (RuntimeException) {
                continue;
            }

            $modifie = false;
            $donnees = $this->suivreLiens($donnees, $ancien, $nouveau, $modifie);

            if ($modifie) {
                $this->content->save($nom, $donnees);
            }
        }
    }

    /**
     * @param array<mixed> $donnees
     * @return array<mixed>
     */
    private function suivreLiens(array $donnees, string $ancien, string $nouveau, bool &$modifie): array
    {
        foreach ($donnees as $cle => $valeur) {
            if (is_array($valeur)) {
                $donnees[$cle] = $this->suivreLiens($valeur, $ancien, $nouveau, $modifie);
                continue;
            }
            if ($cle !== 'url' || !is_string($valeur)) {
                continue;
            }

            if ($valeur === $ancien) {
                $donnees[$cle] = $nouveau;
                $modifie = true;
            } elseif (str_starts_with($valeur, $ancien . '/')) {
                $donnees[$cle] = $nouveau . substr($valeur, strlen($ancien));
                $modifie = true;
            }
        }

        return $donnees;
    }

    // ------------------------------------------------------- plan et robots

    /**
     * Plan du site, limité aux pages indexables et aux fiches publiées.
     */
    public function sitemap(string $base): string
    {
        $base = rtrim($base, '/');
        $urls = [];

        foreach ($this->pages() as $cle => $page) {
            if (!$this->indexable($cle)) {
                continue;
            }
            $urls[] = [
                'loc'      => $base . $this->chemin($cle),
                'priorite' => $cle === 'accueil' ? '1.0' : '0.8',
            ];
        }

        foreach (self::COLLECTIONS as $cle => $collection) {
            if (!$this->indexable($cle)) {
                continue;
            }
            foreach ($this->content->publies($collection) as $item) {
                if (($item['slug'] ?? '') === '') {
                    continue;
                }
                $urls[] = [
                    'loc'      => $base . $this->chemin($cle, $item['slug']),
                    'priorite' => '0.7',
                ];
            }
        }

        $lignes = ['<?xml version="1.0" encoding="UTF-8"?>',
                   '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        foreach ($urls as $u) {
            $lignes[] = '  <url>';
            $lignes[] = '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>';
            $lignes[] = '    <changefreq>weekly</changefreq>';
            $lignes[] = '    <priority>' . $u['priorite'] . '</priority>';
            $lignes[] = '  </url>';
        }
        $lignes[] = '</urlset>';

        return implode("\n", $lignes) . "\n";
    }

    // -------------------------------------------------- données structurées

    /**
     * Données structurées de la page au format JSON-LD.
     *
     * Décrit le domaine comme établissement d'hébergement, la fiche affichée
     * le cas échéant, et le fil d'Ariane — de quoi permettre aux moteurs
     * d'afficher tarifs, coordonnées et arborescence.
     *
     * @param array<string, mixed>|null $item fiche affichée, s'il y en a une
     * @param callable(string): string $media  chemin de photo => adresse absolue
     */
    public function jsonLd(string $cle, ?array $item, string $base, callable $media): string
    {
        $base    = rtrim($base, '/');
        $site    = $this->content->load('site');
        $domaine = $base . '/#domaine';

        $graphe = [$this->noeudDomaine($site, $base, $domaine, $media)];

        if ($item !== null) {
            $graphe[] = $this->noeudFiche($cle, $item, $base, $domaine, $media);
        }

        $fil = $this->filAriane($cle, $item, $base);
        if ($fil !== null) {
            $graphe[] = $fil;
        }

        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graphe],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    /**
     * @param array<string, mixed> $site
     * @param callable(string): string $media
     * @return array<string, mixed>
     */
    private function noeudDomaine(array $site, string $base, string $id, callable $media): array
    {
        $telephone = preg_replace('/\D+/', '', (string) ($site['contact']['telephone'] ?? '')) ?? '';
        if ($telephone !== '' && str_starts_with($telephone, '0')) {
            $telephone = '+33' . substr($telephone, 1);
        }

        // tarif d'entrée du domaine : le plus bas des hébergements publiés
        $prix = array_filter(array_map(
            static fn(array $h): int => (int) ($h['prix_a_partir_de'] ?? 0),
            $this->content->publies('hebergements')
        ));

        $noeud = [
            '@type'       => 'LodgingBusiness',
            '@id'         => $id,
            'name'        => 'Domaine de l’' . (string) ($site['nom'] ?? ''),
            'description' => (string) ($site['accroche'] ?? ''),
            'url'         => $base . '/',
            'address'     => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => (string) ($site['adresse']['rue'] ?? ''),
                'postalCode'      => (string) ($site['adresse']['cp'] ?? ''),
                'addressLocality' => (string) ($site['adresse']['ville'] ?? ''),
                'addressCountry'  => 'FR',
            ],
            'image'       => $media($this->imagePartage()),
            'petsAllowed' => false,
        ];

        if ($telephone !== '') {
            $noeud['telephone'] = $telephone;
        }
        if (($site['contact']['email'] ?? '') !== '') {
            $noeud['email'] = (string) $site['contact']['email'];
        }
        if ($prix !== []) {
            $noeud['priceRange'] = 'À partir de ' . min($prix) . ' €';
        }

        return $noeud;
    }

    /**
     * @param array<string, mixed> $item
     * @param callable(string): string $media
     * @return array<string, mixed>
     */
    private function noeudFiche(string $cle, array $item, string $base, string $domaine, callable $media): array
    {
        $url = $base . $this->chemin($cle, (string) ($item['slug'] ?? ''));

        if ($cle !== 'hebergements') {
            // les étangs sont un site de loisir, pas un logement
            return [
                '@type'       => 'TouristAttraction',
                '@id'         => $url . '#fiche',
                'name'        => (string) ($item['nom'] ?? ''),
                'description' => (string) ($item['resume'] ?? ''),
                'url'         => $url,
                'image'       => $media((string) ($item['image'] ?? '')),
                'isPartOf'    => ['@id' => $domaine],
            ];
        }

        $noeud = [
            '@type'              => 'Accommodation',
            '@id'                => $url . '#fiche',
            'name'               => (string) ($item['nom'] ?? ''),
            'description'        => (string) ($item['resume'] ?? ''),
            'url'                => $url,
            'image'              => $media((string) ($item['image'] ?? '')),
            'containedInPlace'   => ['@id' => $domaine],
            'occupancy'          => [
                '@type' => 'QuantitativeValue',
                'value' => (int) ($item['capacite'] ?? 0),
                'unitText' => 'personnes',
            ],
        ];

        $prix = (int) ($item['prix_a_partir_de'] ?? 0);
        if ($prix > 0) {
            $noeud['offers'] = [
                '@type'         => 'Offer',
                'price'         => $prix,
                'priceCurrency' => 'EUR',
                'url'           => $url,
                'availability'  => 'https://schema.org/InStock',
                'description'   => 'Tarif à partir de, pour une nuit.',
            ];
        }

        $equipements = [];
        foreach ($item['prestations']['groupes'] ?? [] as $groupe) {
            foreach ($groupe['items'] ?? [] as $ligne) {
                $equipements[] = ['@type' => 'LocationFeatureSpecification',
                                  'name'  => (string) $ligne, 'value' => true];
            }
        }
        if ($equipements !== []) {
            $noeud['amenityFeature'] = array_slice($equipements, 0, 25);
        }

        return $noeud;
    }

    /**
     * @param array<string, mixed>|null $item
     * @return array<string, mixed>|null
     */
    private function filAriane(string $cle, ?array $item, string $base): ?array
    {
        if ($cle === 'accueil' && $item === null) {
            return null;
        }

        $etapes = [['name' => 'Accueil', 'url' => $base . '/']];
        $etapes[] = ['name' => $this->page($cle)['nom'], 'url' => $base . $this->chemin($cle)];

        if ($item !== null) {
            $etapes[] = [
                'name' => (string) ($item['nom'] ?? ''),
                'url'  => $base . $this->chemin($cle, (string) ($item['slug'] ?? '')),
            ];
        }

        $elements = [];
        foreach ($etapes as $rang => $etape) {
            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $rang + 1,
                'name'     => $etape['name'],
                'item'     => $etape['url'],
            ];
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $elements];
    }

    public function robots(string $base): string
    {
        $base = rtrim($base, '/');

        if (!$this->tout()['indexer_site']) {
            return "User-agent: *\nDisallow: /\n";
        }

        $lignes = ['User-agent: *', 'Disallow: /admin', 'Disallow: /api'];
        foreach ($this->pages() as $cle => $page) {
            if (!$page['indexer'] && $cle !== 'accueil') {
                $lignes[] = 'Disallow: ' . $this->chemin($cle);
            }
        }
        $lignes[] = '';
        $lignes[] = 'Sitemap: ' . $base . '/sitemap.xml';

        return implode("\n", $lignes) . "\n";
    }
}
