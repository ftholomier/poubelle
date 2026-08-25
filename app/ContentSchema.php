<?php
declare(strict_types=1);

/**
 * Schéma déclaratif du contenu éditable.
 * Le back-office génère automatiquement ses formulaires à partir d'ici :
 * ajouter un champ ci-dessous suffit à le rendre éditable côté admin.
 *
 * Types : text | textarea | number | tags | textlist | list | fields | select
 */
final class ContentSchema
{
    public static function all(): array
    {
        return [
            'hero' => [
                'label' => 'Bandeau d’accueil',
                'icon' => 'star',
                'help' => 'Première chose que voit le visiteur : la promesse et le premier appel à l’action.',
                'fields' => [
                    'badge' => ['label' => 'Pastille', 'type' => 'text'],
                    'title_before' => ['label' => 'Titre — début', 'type' => 'text'],
                    'rotating' => ['label' => 'Mots qui défilent', 'type' => 'tags', 'help' => 'Animés en boucle dans le titre.'],
                    'title_after' => ['label' => 'Titre — fin', 'type' => 'text'],
                    'lead' => ['label' => 'Accroche', 'type' => 'textarea'],
                    'cta_primary' => ['label' => 'Bouton principal', 'type' => 'text'],
                    'cta_secondary' => ['label' => 'Bouton secondaire', 'type' => 'text'],
                    'cta_note' => ['label' => 'Mention sous les boutons', 'type' => 'text'],
                    'proofs' => ['label' => 'Preuves rapides', 'type' => 'tags'],
                ],
            ],
            'marquee' => [
                'label' => 'Bandeau défilant',
                'icon' => 'move',
                'root' => ['label' => 'Éléments défilants', 'type' => 'tags'],
            ],
            'stats' => [
                'label' => 'Chiffres clés',
                'icon' => 'chart',
                'root' => [
                    'label' => 'Compteurs animés',
                    'type' => 'list',
                    'item' => [
                        'value' => ['label' => 'Valeur', 'type' => 'number'],
                        'suffix' => ['label' => 'Suffixe', 'type' => 'text'],
                        'label' => ['label' => 'Libellé', 'type' => 'text'],
                        'sub' => ['label' => 'Précision', 'type' => 'text'],
                    ],
                ],
            ],
            'problem' => [
                'label' => 'Problème / promesse',
                'icon' => 'spark',
                'fields' => [
                    'eyebrow' => ['label' => 'Sur-titre', 'type' => 'text'],
                    'title' => ['label' => 'Titre', 'type' => 'text'],
                    'lead' => ['label' => 'Accroche', 'type' => 'textarea'],
                    'cta' => ['label' => 'Bouton', 'type' => 'text'],
                    'items' => ['label' => 'Frustrations → réponses', 'type' => 'list', 'item' => [
                        'pain' => ['label' => 'Frustration', 'type' => 'text'],
                        'gain' => ['label' => 'Réponse Suisse Immo', 'type' => 'text'],
                    ]],
                ],
            ],
            'benefits' => [
                'label' => 'Avantages Suisse Immo',
                'icon' => 'gift',
                'fields' => [
                    'eyebrow' => ['label' => 'Sur-titre', 'type' => 'text'],
                    'title' => ['label' => 'Titre', 'type' => 'text'],
                    'lead' => ['label' => 'Accroche', 'type' => 'textarea'],
                    'items' => ['label' => 'Avantages', 'type' => 'list', 'item' => [
                        'icon' => ['label' => 'Icône', 'type' => 'select', 'options' => ['network', 'shield', 'rocket', 'hands', 'book', 'tools', 'chart', 'coins']],
                        'title' => ['label' => 'Titre', 'type' => 'text'],
                        'text' => ['label' => 'Texte', 'type' => 'textarea'],
                        'tag' => ['label' => 'Étiquette', 'type' => 'text'],
                    ]],
                ],
            ],
            'values' => [
                'label' => 'Valeurs',
                'icon' => 'heart',
                'fields' => [
                    'eyebrow' => ['label' => 'Sur-titre', 'type' => 'text'],
                    'items' => ['label' => 'Valeurs', 'type' => 'list', 'item' => [
                        'title' => ['label' => 'Titre', 'type' => 'text'],
                        'text' => ['label' => 'Texte', 'type' => 'textarea'],
                    ]],
                ],
            ],
            'compare' => [
                'label' => 'Comparatif concurrence',
                'icon' => 'columns',
                'fields' => [
                    'eyebrow' => ['label' => 'Sur-titre', 'type' => 'text'],
                    'title' => ['label' => 'Titre', 'type' => 'text'],
                    'lead' => ['label' => 'Accroche', 'type' => 'textarea'],
                    'columns' => ['label' => 'En-têtes de colonnes', 'type' => 'tags', 'help' => 'Trois colonnes ; la dernière est mise en avant.'],
                    'rows' => ['label' => 'Lignes', 'type' => 'list', 'item' => [
                        'label' => ['label' => 'Critère', 'type' => 'text'],
                        'a' => ['label' => 'Colonne 1', 'type' => 'text'],
                        'b' => ['label' => 'Colonne 2', 'type' => 'text'],
                        'c' => ['label' => 'Colonne 3 (Suisse Immo)', 'type' => 'text'],
                    ]],
                ],
            ],
            'simulator' => [
                'label' => 'Simulateur de revenus',
                'icon' => 'calc',
                'help' => 'Barème de rémunération utilisé par le calculateur du site. Ajustez-le à votre grille réelle.',
                'fields' => [
                    'eyebrow' => ['label' => 'Sur-titre', 'type' => 'text'],
                    'title' => ['label' => 'Titre', 'type' => 'text'],
                    'lead' => ['label' => 'Accroche', 'type' => 'textarea'],
                    'agency_fee_rate' => ['label' => 'Honoraires d’agence (% du prix de vente)', 'type' => 'number', 'step' => '0.1'],
                    'avg_price' => ['label' => 'Curseur — prix moyen', 'type' => 'fields', 'item' => [
                        'label' => ['label' => 'Libellé', 'type' => 'text'],
                        'min' => ['label' => 'Minimum', 'type' => 'number'],
                        'max' => ['label' => 'Maximum', 'type' => 'number'],
                        'step' => ['label' => 'Pas', 'type' => 'number'],
                        'default' => ['label' => 'Valeur par défaut', 'type' => 'number'],
                    ]],
                    'sales' => ['label' => 'Curseur — ventes/an', 'type' => 'fields', 'item' => [
                        'label' => ['label' => 'Libellé', 'type' => 'text'],
                        'min' => ['label' => 'Minimum', 'type' => 'number'],
                        'max' => ['label' => 'Maximum', 'type' => 'number'],
                        'step' => ['label' => 'Pas', 'type' => 'number'],
                        'default' => ['label' => 'Valeur par défaut', 'type' => 'number'],
                    ]],
                    'tiers' => ['label' => 'Paliers de rémunération', 'type' => 'list', 'item' => [
                        'name' => ['label' => 'Nom du palier', 'type' => 'text'],
                        'from' => ['label' => 'À partir de (ventes/an)', 'type' => 'number'],
                        'to' => ['label' => 'Jusqu’à (ventes/an)', 'type' => 'number'],
                        'rate' => ['label' => 'Part reversée (%)', 'type' => 'number'],
                    ]],
                    'cta' => ['label' => 'Bouton', 'type' => 'text'],
                    'disclaimer' => ['label' => 'Mention légale', 'type' => 'textarea', 'help' => '{rate} est remplacé par le taux d’honoraires.'],
                ],
            ],
            'missions' => [
                'label' => 'Le métier / missions',
                'icon' => 'briefcase',
                'fields' => [
                    'eyebrow' => ['label' => 'Sur-titre', 'type' => 'text'],
                    'title' => ['label' => 'Titre', 'type' => 'text'],
                    'lead' => ['label' => 'Accroche', 'type' => 'textarea'],
                    'items' => ['label' => 'Missions', 'type' => 'list', 'item' => [
                        'title' => ['label' => 'Titre', 'type' => 'text'],
                        'short' => ['label' => 'Résumé', 'type' => 'text'],
                        'text' => ['label' => 'Texte', 'type' => 'textarea'],
                    ]],
                ],
            ],
            'skills' => [
                'label' => 'Compétences recherchées',
                'icon' => 'user',
                'fields' => [
                    'eyebrow' => ['label' => 'Sur-titre', 'type' => 'text'],
                    'title' => ['label' => 'Titre', 'type' => 'text'],
                    'lead' => ['label' => 'Accroche', 'type' => 'textarea'],
                    'items' => ['label' => 'Compétences', 'type' => 'list', 'item' => [
                        'title' => ['label' => 'Titre', 'type' => 'text'],
                        'text' => ['label' => 'Texte', 'type' => 'textarea'],
                    ]],
                ],
            ],
            'steps' => [
                'label' => 'Parcours de candidature',
                'icon' => 'route',
                'fields' => [
                    'eyebrow' => ['label' => 'Sur-titre', 'type' => 'text'],
                    'title' => ['label' => 'Titre', 'type' => 'text'],
                    'lead' => ['label' => 'Accroche', 'type' => 'textarea'],
                    'items' => ['label' => 'Étapes', 'type' => 'list', 'item' => [
                        'n' => ['label' => 'Numéro', 'type' => 'text'],
                        'title' => ['label' => 'Titre', 'type' => 'text'],
                        'text' => ['label' => 'Texte', 'type' => 'textarea'],
                        'duration' => ['label' => 'Durée', 'type' => 'text'],
                    ]],
                ],
            ],
            'network' => [
                'label' => 'Le réseau Suisse Immo',
                'icon' => 'building',
                'fields' => [
                    'eyebrow' => ['label' => 'Sur-titre', 'type' => 'text'],
                    'title' => ['label' => 'Titre', 'type' => 'text'],
                    'lead' => ['label' => 'Accroche', 'type' => 'textarea'],
                    'story_title' => ['label' => 'Titre de l’histoire', 'type' => 'text'],
                    'story' => ['label' => 'Paragraphes', 'type' => 'textlist'],
                    'pillars' => ['label' => 'Piliers', 'type' => 'list', 'item' => [
                        'n' => ['label' => 'Numéro', 'type' => 'text'],
                        'title' => ['label' => 'Titre', 'type' => 'text'],
                        'text' => ['label' => 'Texte', 'type' => 'textarea'],
                    ]],
                    'cities' => ['label' => 'Villes', 'type' => 'tags'],
                ],
            ],
            'testimonials' => [
                'label' => 'Avis clients',
                'icon' => 'quote',
                'fields' => [
                    'eyebrow' => ['label' => 'Sur-titre', 'type' => 'text'],
                    'title' => ['label' => 'Titre', 'type' => 'text'],
                    'lead' => ['label' => 'Accroche', 'type' => 'textarea'],
                    'items' => ['label' => 'Avis', 'type' => 'list', 'item' => [
                        'author' => ['label' => 'Auteur', 'type' => 'text'],
                        'source' => ['label' => 'Source', 'type' => 'text'],
                        'rating' => ['label' => 'Note /5', 'type' => 'number'],
                        'text' => ['label' => 'Avis', 'type' => 'textarea'],
                    ]],
                ],
            ],
            'faq' => [
                'label' => 'Questions fréquentes',
                'icon' => 'help',
                'fields' => [
                    'eyebrow' => ['label' => 'Sur-titre', 'type' => 'text'],
                    'title' => ['label' => 'Titre', 'type' => 'text'],
                    'items' => ['label' => 'Questions', 'type' => 'list', 'item' => [
                        'q' => ['label' => 'Question', 'type' => 'text'],
                        'a' => ['label' => 'Réponse', 'type' => 'textarea'],
                    ]],
                ],
            ],
            'final_cta' => [
                'label' => 'Appel à l’action final',
                'icon' => 'target',
                'fields' => [
                    'eyebrow' => ['label' => 'Sur-titre', 'type' => 'text'],
                    'title' => ['label' => 'Titre', 'type' => 'text'],
                    'lead' => ['label' => 'Accroche', 'type' => 'textarea'],
                    'cta' => ['label' => 'Bouton principal', 'type' => 'text'],
                    'secondary' => ['label' => 'Lien secondaire', 'type' => 'text'],
                    'reassurance' => ['label' => 'Réassurance', 'type' => 'tags'],
                ],
            ],
            'apply' => [
                'label' => 'Tunnel de candidature',
                'icon' => 'form',
                'fields' => [
                    'title' => ['label' => 'Titre', 'type' => 'text'],
                    'lead' => ['label' => 'Accroche', 'type' => 'textarea'],
                    'steps' => ['label' => 'Étapes', 'type' => 'list', 'item' => [
                        'key' => ['label' => 'Clé technique', 'type' => 'text'],
                        'title' => ['label' => 'Titre', 'type' => 'text'],
                        'hint' => ['label' => 'Sous-titre', 'type' => 'text'],
                    ]],
                    'situations' => ['label' => 'Situations proposées', 'type' => 'tags'],
                    'availabilities' => ['label' => 'Disponibilités proposées', 'type' => 'tags'],
                    'sources' => ['label' => 'Origines proposées', 'type' => 'tags'],
                    'success_title' => ['label' => 'Titre de confirmation', 'type' => 'text'],
                    'success_text' => ['label' => 'Texte de confirmation', 'type' => 'textarea'],
                ],
            ],
            'contact' => [
                'label' => 'Page contact',
                'icon' => 'mail',
                'fields' => [
                    'title' => ['label' => 'Titre', 'type' => 'text'],
                    'lead' => ['label' => 'Accroche', 'type' => 'textarea'],
                ],
            ],
        ];
    }

    /** Reconstruit une section à partir des données postées. */
    public static function hydrate(array $spec, mixed $posted): mixed
    {
        $type = $spec['type'] ?? 'text';
        switch ($type) {
            case 'number':
                return is_numeric($posted) ? (0 + $posted) : 0;
            case 'tags':
            case 'textlist':
                if (is_string($posted)) {
                    $sep = $type === 'tags' ? "\n" : "\n\n";
                    $parts = preg_split('/' . ($type === 'tags' ? '\r?\n' : '\r?\n\r?\n') . '/', $posted) ?: [];
                    return array_values(array_filter(array_map('trim', $parts), static fn ($v) => $v !== ''));
                }
                return is_array($posted) ? array_values(array_filter(array_map('trim', $posted))) : [];
            case 'list':
                $out = [];
                if (is_array($posted)) {
                    foreach ($posted as $row) {
                        if (!is_array($row)) { continue; }
                        $item = [];
                        $empty = true;
                        foreach ($spec['item'] as $k => $sub) {
                            $item[$k] = self::hydrate($sub, $row[$k] ?? null);
                            if ($item[$k] !== '' && $item[$k] !== [] && $item[$k] !== 0) { $empty = false; }
                        }
                        if (!$empty) { $out[] = $item; }
                    }
                }
                return $out;
            case 'fields':
                $out = [];
                foreach ($spec['item'] as $k => $sub) {
                    $out[$k] = self::hydrate($sub, is_array($posted) ? ($posted[$k] ?? null) : null);
                }
                return $out;
            default:
                return is_string($posted) ? trim($posted) : '';
        }
    }
}
