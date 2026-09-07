<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Catalogue des blocs de page.
 *
 * Chaque type déclare son schéma par défaut : c'est ce schéma qui est fusionné
 * à la lecture. Ajouter un champ ici ne casse donc aucun contenu existant, et
 * supprimer un bloc du catalogue n'efface pas les données déjà saisies —
 * le bloc est simplement ignoré au rendu.
 */
final class Blocks
{
    /** @return array<string,array{label:string,icon:string,schema:array<string,mixed>}> */
    public static function catalog(): array
    {
        return [
            'hero' => [
                'label' => 'Bannière principale',
                'icon'  => 'hero',
                'schema' => [
                    'eyebrow'   => ['fr' => ''],
                    'title'     => ['fr' => ''],
                    'highlight' => ['fr' => ''],
                    'text'      => ['fr' => ''],
                    'primary_label' => ['fr' => ''],
                    'primary_url'   => '/contact',
                    'secondary_label' => ['fr' => ''],
                    'secondary_url'   => '#services',
                    'image'     => '',
                    'image_alt' => ['fr' => ''],
                    'badges'    => [],   // [{label:{fr}}]
                    'variant'   => 'split', // split | centered
                ],
            ],
            'stats' => [
                'label' => 'Chiffres clés',
                'icon'  => 'stats',
                'schema' => [
                    'title' => ['fr' => ''],
                    'items' => [], // [{value, suffix, prefix, label:{fr}}]
                ],
            ],
            'services' => [
                'label' => 'Prestations',
                'icon'  => 'services',
                'schema' => [
                    'eyebrow' => ['fr' => ''],
                    'title'   => ['fr' => ''],
                    'text'    => ['fr' => ''],
                    'columns' => 3,
                    'items'   => [], // [{icon, title:{fr}, text:{fr}, url, link_label:{fr}}]
                ],
            ],
            'steps' => [
                'label' => 'Méthode / étapes',
                'icon'  => 'steps',
                'schema' => [
                    'eyebrow' => ['fr' => ''],
                    'title'   => ['fr' => ''],
                    'text'    => ['fr' => ''],
                    'items'   => [], // [{title:{fr}, text:{fr}}]
                ],
            ],
            'media_text' => [
                'label' => 'Image + texte',
                'icon'  => 'media',
                'schema' => [
                    'eyebrow'  => ['fr' => ''],
                    'title'    => ['fr' => ''],
                    'html'     => ['fr' => ''],
                    'image'    => '',
                    'image_alt'=> ['fr' => ''],
                    'side'     => 'right', // left | right
                    'cta_label'=> ['fr' => ''],
                    'cta_url'  => '',
                    'bullets'  => [], // [{text:{fr}}]
                ],
            ],
            'richtext' => [
                'label' => 'Texte libre (WYSIWYG)',
                'icon'  => 'text',
                'schema' => [
                    'title'  => ['fr' => ''],
                    'html'   => ['fr' => ''],
                    'narrow' => true,
                ],
            ],
            'reviews' => [
                'label' => 'Avis Google',
                'icon'  => 'star',
                'schema' => [
                    'eyebrow' => ['fr' => ''],
                    'title'   => ['fr' => ''],
                    'text'    => ['fr' => ''],
                    'limit'   => 6,
                ],
            ],
            'faq' => [
                'label' => 'Questions fréquentes',
                'icon'  => 'faq',
                'schema' => [
                    'eyebrow' => ['fr' => ''],
                    'title'   => ['fr' => ''],
                    'items'   => [], // [{question:{fr}, answer:{fr}}]
                ],
            ],
            'cta_band' => [
                'label' => 'Bandeau d’appel à l’action',
                'icon'  => 'cta',
                'schema' => [
                    'title'  => ['fr' => ''],
                    'text'   => ['fr' => ''],
                    'primary_label' => ['fr' => ''],
                    'primary_url'   => '/contact?sujet=accompagnement',
                    'secondary_label' => ['fr' => ''],
                    'secondary_url'   => '/contact',
                    'variant' => 'dark', // dark | accent | light
                ],
            ],
            'contact' => [
                'label' => 'Formulaire de contact',
                'icon'  => 'mail',
                'schema' => [
                    'eyebrow'  => ['fr' => ''],
                    'title'    => ['fr' => ''],
                    'text'     => ['fr' => ''],
                    'show_map' => true,
                    'show_info'=> true,
                ],
            ],
            'logos' => [
                'label' => 'Références / confiance',
                'icon'  => 'logos',
                'schema' => [
                    'title' => ['fr' => ''],
                    'items' => [], // [{image, alt}]
                ],
            ],
            'posts' => [
                'label' => 'Dernières actualités',
                'icon'  => 'news',
                'schema' => [
                    'eyebrow' => ['fr' => ''],
                    'title'   => ['fr' => ''],
                    'limit'   => 3,
                ],
            ],
        ];
    }

    public static function exists(string $type): bool
    {
        return array_key_exists($type, self::catalog());
    }

    /** @return array<string,mixed> */
    public static function schemaFor(string $type): array
    {
        return self::catalog()[$type]['schema'] ?? [];
    }

    public static function labelFor(string $type): string
    {
        return self::catalog()[$type]['label'] ?? ucfirst($type);
    }

    /** Enveloppe commune à tous les blocs. */
    public static function wrapper(): array
    {
        return [
            'id'      => '',
            'type'    => 'richtext',
            'enabled' => true,
            'anchor'  => '',
            'spacing' => 'normal',  // tight | normal | loose
            'theme'   => 'light',   // light | surface | dark
            'data'    => [],
        ];
    }

    /**
     * Normalise un bloc : enveloppe + schéma spécifique au type.
     * Un type inconnu est conservé tel quel mais désactivé au rendu.
     *
     * @param array<string,mixed> $block
     * @return array<string,mixed>
     */
    public static function normalize(array $block): array
    {
        // Les données sont mises de côté : leur schéma dépend du type de bloc,
        // et l'enveloppe ne doit surtout pas les ré-indexer.
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        unset($block['data']);

        $wrapper = self::wrapper();
        unset($wrapper['data']);
        $block = \App\Core\Schema::normalize($block, $wrapper);

        $type = (string) $block['type'];
        if ($block['id'] === '') {
            $block['id'] = bin2hex(random_bytes(6));
        }
        if (!self::exists($type)) {
            $block['data'] = $data;
            $block['_unknown'] = true;
            return $block;
        }

        $block['data'] = \App\Core\Schema::normalize($data, self::schemaFor($type));
        return $block;
    }

    /** Icônes disponibles pour les cartes de prestation (SVG flat, cf. views/front/icon.php). */
    public static function icons(): array
    {
        return [
            'glasses', 'chart', 'shield', 'handshake', 'calculator', 'growth',
            'compass', 'target', 'clock', 'building', 'spark', 'document',
        ];
    }
}
