<?php
declare(strict_types=1);

namespace App;

/**
 * Aides du back-office : menu, écrans, et surtout le schéma d'édition des
 * pages. Le schéma décrit les champs éditables ; les vues le rendent et
 * l'enregistrement le rejoue, ce qui garantit qu'aucune clé technique du JSON
 * n'est écrasée par le formulaire.
 */
final class Admin
{
    public const MENU = [
        'dash' => ['label' => 'Tableau de bord', 'title' => 'Tableau de bord', 'sub' => "Vue d'ensemble du site et des demandes entrantes"],
        'pages' => ['label' => 'Pages & contenus', 'title' => 'Pages & contenus', 'sub' => 'Édition champ par champ, brouillon puis publication, une version par enregistrement'],
        'offices' => ['label' => 'Bureaux & dispos', 'title' => 'Bureaux & dispos', 'sub' => 'Ce tableau pilote directement la page « Nos bureaux » du site'],
        'media' => ['label' => 'Photos', 'title' => 'Photothèque', 'sub' => 'Photos des espaces et des bureaux, textes alternatifs et légendes'],
        'posts' => ['label' => "L'actu", 'title' => "L'actu", 'sub' => 'Articles publiés sur le site et repris par l’assistant'],
        'requests' => ['label' => 'Demandes', 'title' => 'Demandes', 'sub' => 'Contacts, réservations et rappels de disponibilités'],
        'docs' => ['label' => 'Assistant IA', 'title' => 'Assistant IA', 'sub' => 'Documents, prompt système et questions restées sans réponse'],
        'settings' => ['label' => 'Réglages & clés', 'title' => 'Réglages & clés API', 'sub' => 'Identité du site, coordonnées, intégrations et comptes'],
    ];

    /** Pages éditables, dans l'ordre d'affichage. */
    public const PAGES = [
        'home' => 'Accueil',
        'spaces' => 'Nos espaces',
        'offices' => 'Nos bureaux',
        'news' => "L'actu",
        'contact' => 'Contact',
        'legal' => 'Mentions légales',
        'privacy' => 'Politique de confidentialité',
    ];

    /**
     * Schéma d'édition d'une page : sections → champs.
     * type : text | textarea | rich | list | media | repeat | color
     */
    public static function pageSchema(string $slug): array
    {
        $seo = ['title' => 'Référencement', 'fields' => [
            ['path' => 'seo.title', 'label' => 'Titre SEO', 'type' => 'text', 'hint' => 'Affiché dans l’onglet du navigateur et sur Google (60 caractères conseillés).'],
            ['path' => 'seo.description', 'label' => 'Description SEO', 'type' => 'textarea', 'hint' => '150 à 160 caractères.'],
            ['path' => 'seo.ogImage', 'label' => 'Image de partage', 'type' => 'media', 'max' => 1],
        ]];

        $schemas = [
            'home' => [
                $seo,
                ['title' => 'Hero', 'fields' => [
                    ['path' => 'hero.badge', 'label' => 'Pastille', 'type' => 'text', 'hint' => '{count} est remplacé par le nombre réel de postes libres.'],
                    ['path' => 'hero.title1', 'label' => 'Titre — ligne 1', 'type' => 'text'],
                    ['path' => 'hero.title2', 'label' => 'Titre — ligne 2', 'type' => 'text'],
                    ['path' => 'hero.highlight', 'label' => 'Titre — mots surlignés en jaune', 'type' => 'text'],
                    ['path' => 'hero.text', 'label' => 'Accroche', 'type' => 'textarea'],
                    ['path' => 'hero.ctaPrimary.label', 'label' => 'Bouton principal', 'type' => 'text'],
                    ['path' => 'hero.ctaSecondary.label', 'label' => 'Bouton secondaire', 'type' => 'text'],
                    ['path' => 'hero.slides', 'label' => 'Diaporama', 'type' => 'media'],
                    ['path' => 'hero.stats', 'label' => 'Chiffres clés', 'type' => 'repeat', 'fields' => [
                        ['path' => 'value', 'label' => 'Valeur', 'type' => 'text'],
                        ['path' => 'label', 'label' => 'Légende', 'type' => 'text'],
                    ]],
                    ['path' => 'hero.cardPrice.label', 'label' => 'Étiquette prix', 'type' => 'text'],
                    ['path' => 'hero.cardPrice.note', 'label' => 'Mention sous le prix', 'type' => 'text'],
                    ['path' => 'hero.cardBadge.kicker', 'label' => 'Vignette — surtitre', 'type' => 'text'],
                    ['path' => 'hero.cardBadge.line1', 'label' => 'Vignette — ligne 1', 'type' => 'text'],
                    ['path' => 'hero.cardBadge.line2', 'label' => 'Vignette — ligne 2', 'type' => 'text'],
                ]],
                ['title' => 'Nos espaces', 'fields' => [
                    ['path' => 'places.kicker', 'label' => 'Surtitre', 'type' => 'text'],
                    ['path' => 'places.title', 'label' => 'Titre', 'type' => 'text'],
                    ['path' => 'places.cta', 'label' => 'Bouton', 'type' => 'text'],
                ]],
                ['title' => 'Disponibilités', 'fields' => [
                    ['path' => 'availability.kicker', 'label' => 'Surtitre', 'type' => 'text'],
                    ['path' => 'availability.title', 'label' => 'Titre', 'type' => 'text'],
                ]],
                ['title' => 'Étapes', 'fields' => [
                    ['path' => 'steps.title', 'label' => 'Titre', 'type' => 'text'],
                    ['path' => 'steps.items', 'label' => 'Étapes', 'type' => 'repeat', 'fields' => [
                        ['path' => 'n', 'label' => 'Numéro', 'type' => 'text'],
                        ['path' => 'title', 'label' => 'Titre', 'type' => 'text'],
                        ['path' => 'text', 'label' => 'Texte', 'type' => 'textarea'],
                        ['path' => 'color', 'label' => 'Fond', 'type' => 'color'],
                    ]],
                ]],
                ['title' => 'Avis et bande de conversion', 'fields' => [
                    ['path' => 'reviews.title', 'label' => 'Titre des avis', 'type' => 'text'],
                    ['path' => 'band.title', 'label' => 'Bande — titre', 'type' => 'text', 'hint' => '{count} = nombre de postes libres.'],
                    ['path' => 'band.text', 'label' => 'Bande — texte', 'type' => 'textarea'],
                    ['path' => 'band.cta1', 'label' => 'Bande — bouton 1', 'type' => 'text'],
                    ['path' => 'band.cta2', 'label' => 'Bande — bouton 2', 'type' => 'text'],
                ]],
                ['title' => 'Questions fréquentes', 'fields' => [
                    ['path' => 'faq.title', 'label' => 'Titre', 'type' => 'text'],
                    ['path' => 'faq.items', 'label' => 'Questions', 'type' => 'repeat', 'fields' => [
                        ['path' => 'q', 'label' => 'Question', 'type' => 'text'],
                        ['path' => 'a', 'label' => 'Réponse', 'type' => 'textarea'],
                    ]],
                ]],
            ],
            'spaces' => [
                $seo,
                ['title' => 'En-tête', 'fields' => [
                    ['path' => 'kicker', 'label' => 'Surtitre', 'type' => 'text'],
                    ['path' => 'title', 'label' => 'Titre', 'type' => 'text'],
                    ['path' => 'text', 'label' => 'Accroche', 'type' => 'textarea'],
                ]],
                ['title' => 'Les deux lieux', 'fields' => [
                    ['path' => 'spaces', 'label' => 'Espaces', 'type' => 'repeat', 'fields' => [
                        ['path' => 'id', 'label' => 'Identifiant du lieu (carnot / granvelle)', 'type' => 'text'],
                        ['path' => 'tag', 'label' => 'Étiquette', 'type' => 'text'],
                        ['path' => 'text', 'label' => 'Description', 'type' => 'textarea'],
                        ['path' => 'cta', 'label' => 'Bouton', 'type' => 'text'],
                        ['path' => 'bg', 'label' => 'Fond de section', 'type' => 'color'],
                        ['path' => 'access', 'label' => 'À proximité', 'type' => 'list'],
                        ['path' => 'photos', 'label' => 'Photos (3 : grande, puis deux petites)', 'type' => 'media'],
                        ['path' => 'amenities', 'label' => 'Équipements', 'type' => 'repeat', 'fields' => [
                            ['path' => 'title', 'label' => 'Titre', 'type' => 'text'],
                            ['path' => 'text', 'label' => 'Texte', 'type' => 'textarea'],
                            ['path' => 'color', 'label' => 'Couleur', 'type' => 'color'],
                        ]],
                    ]],
                ]],
            ],
            'offices' => [
                $seo,
                ['title' => 'En-tête', 'fields' => [
                    ['path' => 'kicker', 'label' => 'Surtitre', 'type' => 'text'],
                    ['path' => 'title', 'label' => 'Titre', 'type' => 'text'],
                    ['path' => 'text', 'label' => 'Accroche', 'type' => 'textarea'],
                ]],
                ['title' => 'Compris dans le loyer', 'fields' => [
                    ['path' => 'included', 'label' => 'Prestations incluses', 'type' => 'list'],
                ]],
            ],
            'news' => [
                $seo,
                ['title' => 'En-tête', 'fields' => [
                    ['path' => 'kicker', 'label' => 'Surtitre', 'type' => 'text'],
                    ['path' => 'title', 'label' => 'Titre', 'type' => 'text'],
                ]],
            ],
            'contact' => [
                $seo,
                ['title' => 'En-tête', 'fields' => [
                    ['path' => 'kicker', 'label' => 'Surtitre', 'type' => 'text'],
                    ['path' => 'title', 'label' => 'Titre', 'type' => 'text'],
                    ['path' => 'text', 'label' => 'Accroche', 'type' => 'textarea'],
                ]],
                ['title' => 'Formulaire', 'fields' => [
                    ['path' => 'needs', 'label' => 'Choix « votre besoin »', 'type' => 'list'],
                ]],
            ],
        ];

        $legal = [
            $seo,
            ['title' => 'En-tête', 'fields' => [
                ['path' => 'kicker', 'label' => 'Surtitre', 'type' => 'text'],
                ['path' => 'title', 'label' => 'Titre', 'type' => 'text'],
                ['path' => 'text', 'label' => 'Chapeau', 'type' => 'textarea'],
            ]],
            ['title' => 'Articles', 'fields' => [
                ['path' => 'blocks', 'label' => 'Blocs', 'type' => 'repeat', 'fields' => [
                    ['path' => 'title', 'label' => 'Titre', 'type' => 'text'],
                    ['path' => 'text', 'label' => 'Texte', 'type' => 'textarea', 'rows' => 6],
                ]],
            ]],
        ];
        $schemas['legal'] = $legal;
        $schemas['privacy'] = $legal;

        return $schemas[$slug] ?? [$seo];
    }

    /** Lit une valeur dans un tableau via un chemin pointé. */
    public static function get(array $data, string $path, mixed $default = null): mixed
    {
        $node = $data;
        foreach (explode('.', $path) as $key) {
            if (!\is_array($node) || !\array_key_exists($key, $node)) {
                return $default;
            }
            $node = $node[$key];
        }
        return $node;
    }

    /** Écrit une valeur via un chemin pointé, en créant les niveaux manquants. */
    public static function set(array &$data, string $path, mixed $value): void
    {
        $node = &$data;
        foreach (explode('.', $path) as $key) {
            if (!isset($node[$key]) || !\is_array($node[$key])) {
                $node[$key] = [];
            }
            $node = &$node[$key];
        }
        $node = $value;
    }

    /**
     * Rejoue le schéma sur les données envoyées par le formulaire et renvoie
     * la page mise à jour. Les clés hors schéma sont conservées telles quelles.
     */
    public static function applySchema(array $page, array $schema, array $input): array
    {
        foreach ($schema as $section) {
            foreach ((array) ($section['fields'] ?? []) as $field) {
                $path = (string) $field['path'];
                $raw = self::get($input, $path);
                $value = self::normalizeField($field, $raw);
                if ($value === null) {
                    continue;
                }
                self::set($page, $path, $value);
            }
        }
        return $page;
    }

    /** Nettoie une valeur reçue selon le type déclaré au schéma. */
    private static function normalizeField(array $field, mixed $raw): mixed
    {
        $type = (string) ($field['type'] ?? 'text');

        return match ($type) {
            'text', 'color' => \is_scalar($raw) ? trim((string) $raw) : null,
            'textarea' => \is_scalar($raw) ? trim(str_replace("\r\n", "\n", (string) $raw)) : null,
            'rich' => \is_scalar($raw) ? Text::sanitizeHtml((string) $raw) : null,
            'media' => self::normalizeMedia($raw, (int) ($field['max'] ?? 0)),
            'list' => \is_string($raw)
                ? array_values(array_filter(array_map('trim', explode("\n", str_replace("\r\n", "\n", $raw)))))
                : null,
            'repeat' => \is_array($raw) ? self::normalizeRepeat((array) ($field['fields'] ?? []), $raw) : null,
            default => null,
        };
    }

    private static function normalizeMedia(mixed $raw, int $max): array|string|null
    {
        if (!\is_string($raw)) {
            return null;
        }
        $paths = array_values(array_filter(array_map('trim', explode(',', $raw))));
        $paths = array_values(array_filter($paths, static fn (string $p): bool => str_starts_with($p, '/')));
        return $max === 1 ? (string) ($paths[0] ?? '') : $paths;
    }

    /** Une ligne entièrement vide est supprimée : c'est ainsi qu'on retire un élément. */
    private static function normalizeRepeat(array $fields, array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $item = [];
            $empty = true;
            foreach ($fields as $field) {
                $value = self::normalizeField($field, self::get($row, (string) $field['path']));
                if ($value === null) {
                    $value = ($field['type'] ?? 'text') === 'repeat' || ($field['type'] ?? '') === 'list' || ($field['type'] ?? '') === 'media' ? [] : '';
                }
                if ($value !== '' && $value !== []) {
                    $empty = false;
                }
                self::set($item, (string) $field['path'], $value);
            }
            if (!$empty) {
                $out[] = $item;
            }
        }
        return $out;
    }

    /** Chaînes traduisibles d'une page, pour la traduction assistée. */
    public static function translatableKeys(): array
    {
        return ['_schema', 'slug', 'lang', 'status', 'updatedAt', 'updatedBy', 'color', 'bg', 'id', 'n', 'photos', 'slides', 'ogImage', 'route', 'href', 'noindex'];
    }

    public static function humanDate(string $iso): string
    {
        $ts = strtotime($iso);
        if ($ts === false) {
            return '—';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return "à l'instant";
        }
        if ($diff < 3600) {
            return 'il y a ' . (int) ($diff / 60) . ' min';
        }
        if ($diff < 86400) {
            return 'il y a ' . (int) ($diff / 3600) . ' h';
        }
        if ($diff < 604800) {
            return 'il y a ' . (int) ($diff / 86400) . ' j';
        }
        return date('d/m/Y à H:i', $ts);
    }

    /** Clés d'intégration pilotables depuis Réglages → Clés API. */
    public static function keyDefs(): array
    {
        return [
            ['key' => 'GEMINI_API_KEY', 'label' => 'Clé API Gemini', 'secret' => true, 'hint' => 'Assistant du site. Sans clé, l’assistant répond à partir de l’index local.'],
            ['key' => 'GEMINI_MODEL', 'label' => 'Modèle Gemini', 'secret' => false, 'hint' => 'Par défaut : gemini-2.5-flash.'],
            ['key' => 'GOOGLE_PLACES_KEY', 'label' => 'Clé API Google Places', 'secret' => true, 'hint' => 'Récupération des avis Google (cache 24 h).'],
            ['key' => 'GOOGLE_PLACE_ID', 'label' => 'Identifiant de la fiche Google', 'secret' => false, 'hint' => 'Place ID de la fiche Google Business Profile.'],
            ['key' => 'GOOGLE_TRANSLATE_KEY', 'label' => 'Clé API Google Translate', 'secret' => true, 'hint' => 'Bouton « Traduire en anglais » du back-office.'],
            ['key' => 'MAIL_FROM', 'label' => 'Adresse expéditrice', 'secret' => false, 'hint' => 'Doit appartenir au domaine du site pour ne pas finir en spam.'],
            ['key' => 'MAIL_TO', 'label' => 'Boîte qui reçoit les demandes', 'secret' => false, 'hint' => 'Par défaut : l’email de contact des réglages.'],
            ['key' => 'SMTP_HOST', 'label' => 'Serveur SMTP', 'secret' => false, 'hint' => 'Laissez vide pour utiliser la fonction mail() de l’hébergeur.'],
            ['key' => 'SMTP_PORT', 'label' => 'Port SMTP', 'secret' => false, 'hint' => '587 en TLS, 465 en SSL.'],
            ['key' => 'SMTP_SECURE', 'label' => 'Chiffrement SMTP', 'secret' => false, 'hint' => 'tls, ssl ou none.'],
            ['key' => 'SMTP_USER', 'label' => 'Utilisateur SMTP', 'secret' => false],
            ['key' => 'SMTP_PASS', 'label' => 'Mot de passe SMTP', 'secret' => true],
        ];
    }

    /** Enregistre les clés dans storage/secrets.json (hors racine web, 0600). */
    public static function saveKeys(array $input, string $by): bool
    {
        $secrets = Config::secrets();
        foreach (self::keyDefs() as $def) {
            $key = $def['key'];
            if (!\array_key_exists($key, $input)) {
                continue;
            }
            if (Config::isLockedByEnv($key)) {
                continue; // une clé posée dans .env n'est jamais écrasée depuis le web
            }
            $value = trim((string) $input[$key]);
            if ($value === '••••••••') {
                continue; // champ masqué laissé tel quel
            }
            if ($value === '') {
                unset($secrets[$key]);
            } else {
                $secrets[$key] = $value;
            }
        }

        $file = Config::storagePath('secrets.json');
        $tmp = $file . '.tmp';
        $json = json_encode($secrets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || @file_put_contents($tmp, $json, LOCK_EX) === false) {
            Log::write('error', 'Écriture des clés impossible.');
            return false;
        }
        @chmod($tmp, 0600);
        $ok = @rename($tmp, $file);
        Config::forgetSecrets();
        Log::write('auth', 'Clés API mises à jour par ' . $by);
        return $ok;
    }
}
