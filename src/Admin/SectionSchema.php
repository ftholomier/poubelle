<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * Description des champs de chaque type de section.
 *
 * Une seule définition sert à trois choses : dessiner le formulaire d'édition,
 * nettoyer ce que le formulaire renvoie, et proposer la liste des types au
 * moment d'ajouter une section. Ajouter un type revient donc à écrire une
 * entrée ici et un gabarit dans views/partials/.
 *
 * Types de champ :
 *   text     une ligne (« keepSpaces » conserve les espaces de bordure)
 *   textarea un paragraphe
 *   lines    une valeur par ligne saisie — sert aux titres à plusieurs lignes
 *   number   un nombre, borné par « min » et « max »
 *   repeater une liste d'entrées ayant chacune leurs propres champs
 */
final class SectionSchema
{
    /**
     * @return array<string, array{label: string, hint: string, fields: array<string, array<string,mixed>>}>
     */
    public static function all(): array
    {
        return [
            'hero' => [
                'label' => 'Grand titre',
                'hint'  => 'Ouverture de page, pleine hauteur.',
                'fields' => [
                    'eyebrow'     => ['type' => 'text', 'label' => 'Sur-titre'],
                    'title'       => ['type' => 'lines', 'label' => 'Titre', 'hint' => 'Une ligne de titre par ligne saisie.'],
                    'outlineFrom' => self::outlineField(),
                    'subtitle'    => ['type' => 'text', 'label' => 'Sous-titre'],
                    'body'        => ['type' => 'textarea', 'label' => 'Texte'],
                ],
            ],

            'statement' => [
                'label' => 'Intertitre',
                'hint'  => 'Un titre et un texte, sur deux colonnes.',
                'fields' => [
                    'eyebrow'     => ['type' => 'text', 'label' => 'Sur-titre'],
                    'title'       => ['type' => 'lines', 'label' => 'Titre'],
                    'outlineFrom' => self::outlineField(),
                    'body'        => ['type' => 'textarea', 'label' => 'Texte'],
                ],
            ],

            'cards' => [
                'label' => 'Grille de cartes',
                'hint'  => 'Une offre, une prestation ou une étape par carte.',
                'fields' => [
                    'eyebrow'     => ['type' => 'text', 'label' => 'Sur-titre'],
                    'title'       => ['type' => 'lines', 'label' => 'Titre'],
                    'outlineFrom' => self::outlineField(),
                    'cards'       => [
                        'type'   => 'repeater',
                        'label'  => 'Cartes',
                        'single' => 'carte',
                        'fields' => [
                            'num'   => ['type' => 'text', 'label' => 'Numéro'],
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'mode'  => ['type' => 'text', 'label' => 'Sous-titre'],
                            'goal'  => ['type' => 'text', 'label' => 'Bénéfice'],
                            'text'  => ['type' => 'textarea', 'label' => 'Description'],
                        ],
                    ],
                ],
            ],

            'columns' => [
                'label' => 'Colonnes de listes',
                'hint'  => 'Domaines d\'expertise, précédés d\'un bandeau défilant.',
                'fields' => [
                    'marquee' => ['type' => 'text', 'label' => 'Texte du bandeau'],
                    'repeat'  => ['type' => 'number', 'label' => 'Répétitions du bandeau', 'min' => 2, 'max' => 8],
                    'speed'   => ['type' => 'number', 'label' => 'Vitesse du bandeau', 'min' => 0, 'max' => 3, 'step' => 0.1],
                    'columns' => [
                        'type'   => 'repeater',
                        'label'  => 'Colonnes',
                        'single' => 'colonne',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'items' => ['type' => 'lines', 'label' => 'Entrées', 'hint' => 'Une entrée par ligne.'],
                        ],
                    ],
                ],
            ],

            'stats' => [
                'label' => 'Chiffres animés',
                'hint'  => 'Les nombres montent quand la section arrive à l\'écran.',
                'fields' => [
                    'eyebrow' => ['type' => 'text', 'label' => 'Sur-titre'],
                    'items'   => [
                        'type'   => 'repeater',
                        'label'  => 'Chiffres',
                        'single' => 'chiffre',
                        'fields' => [
                            'value'  => ['type' => 'number', 'label' => 'Valeur', 'min' => 0, 'max' => 1000000],
                            'suffix' => [
                                'type'       => 'text',
                                'label'      => 'Suffixe',
                                // « 147 € » et non « 147€ » : en français l'unité
                                // se sépare du nombre, l'espace fait partie de la
                                // saisie et ne doit pas être rogné.
                                'keepSpaces' => true,
                                'hint'       => 'Par exemple « € », « % » ou « ans ». L\'espace avant le mot est conservé.',
                            ],
                            'label'  => ['type' => 'text', 'label' => 'Légende'],
                        ],
                    ],
                ],
            ],

            'marquee' => [
                'label' => 'Bandeau défilant',
                'hint'  => 'Un texte en très grand, qui défile avec le scroll.',
                'fields' => [
                    'text'   => ['type' => 'text', 'label' => 'Texte'],
                    'repeat' => ['type' => 'number', 'label' => 'Répétitions', 'min' => 2, 'max' => 8],
                    'speed'  => ['type' => 'number', 'label' => 'Vitesse', 'min' => 0, 'max' => 3, 'step' => 0.1],
                    'body'   => ['type' => 'textarea', 'label' => 'Texte sous le bandeau'],
                ],
            ],

            'formula' => [
                'label' => 'Formule',
                'hint'  => 'Une expression en très grand, en dégradé.',
                'fields' => [
                    'eyebrow' => ['type' => 'text', 'label' => 'Sur-titre'],
                    'formula' => ['type' => 'text', 'label' => 'Formule'],
                    'body'    => ['type' => 'textarea', 'label' => 'Texte'],
                ],
            ],

            'quote' => [
                'label' => 'Citation',
                'hint'  => 'Une phrase forte, attribuée.',
                'fields' => [
                    'eyebrow' => ['type' => 'text', 'label' => 'Sur-titre'],
                    'quote'   => ['type' => 'lines', 'label' => 'Citation'],
                    'author'  => ['type' => 'text', 'label' => 'Auteur'],
                    'role'    => ['type' => 'text', 'label' => 'Fonction'],
                ],
            ],

            'contact' => [
                'label' => 'Prise de contact',
                'hint'  => 'Titre, texte et libellé du bouton. Sa destination vient de site.json.',
                'fields' => [
                    'eyebrow'     => ['type' => 'text', 'label' => 'Sur-titre'],
                    'title'       => ['type' => 'lines', 'label' => 'Titre'],
                    'outlineFrom' => self::outlineField(),
                    'body'        => ['type' => 'textarea', 'label' => 'Texte'],
                    'action'      => [
                        'type'  => 'text',
                        'label' => 'Libellé du bouton',
                        'hint'  => 'Laissé vide, c\'est le libellé commun de site.json qui s\'affiche.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function forKind(string $kind): ?array
    {
        return self::all()[$kind] ?? null;
    }

    public static function isKnownKind(string $kind): bool
    {
        return isset(self::all()[$kind]);
    }

    /**
     * Nettoie les valeurs venues du formulaire selon le schéma du type.
     *
     * Tout champ absent du schéma est écarté : le formulaire ne peut pas
     * introduire de clé arbitraire dans le fichier de contenu. Les champs
     * laissés vides ne sont pas écrits, pour que le JSON reste lisible.
     *
     * @param  array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function sanitize(string $kind, array $input): array
    {
        $schema = self::forKind($kind);
        if ($schema === null) {
            throw new \InvalidArgumentException("Type de section inconnu : « {$kind} »");
        }

        $clean = [];
        foreach ($schema['fields'] as $name => $field) {
            $value = self::sanitizeField($field, $input[$name] ?? null);
            if ($value !== null) {
                $clean[$name] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string,mixed> $field
     * @return mixed null si le champ est vide et ne doit pas être écrit
     */
    private static function sanitizeField(array $field, mixed $value): mixed
    {
        switch ($field['type']) {
            case 'text':
                $text = self::cleanText(is_string($value) ? $value : '', $field['keepSpaces'] ?? false);
                return trim($text) === '' ? null : mb_substr($text, 0, 300);

            case 'textarea':
                $text = self::cleanText(is_string($value) ? $value : '');
                return $text === '' ? null : mb_substr($text, 0, 2000);

            case 'lines':
                $lines = self::toLines($value);
                return $lines === [] ? null : $lines;

            case 'number':
                if ($value === null || $value === '' || !is_numeric($value)) {
                    return null;
                }
                $number = (float) $value;
                $number = max((float) ($field['min'] ?? -1e9), min($number, (float) ($field['max'] ?? 1e9)));
                // Un nombre entier reste entier dans le JSON : « 6 » et non « 6.0 ».
                return floor($number) === $number ? (int) $number : round($number, 3);

            case 'repeater':
                return self::sanitizeRepeater($field, $value);

            default:
                return null;
        }
    }

    /**
     * @param  array<string,mixed> $field
     * @return list<array<string,mixed>>|null
     */
    private static function sanitizeRepeater(array $field, mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $entries = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }
            $entry = [];
            foreach ($field['fields'] as $name => $sub) {
                $clean = self::sanitizeField($sub, $row[$name] ?? null);
                if ($clean !== null) {
                    $entry[$name] = $clean;
                }
            }
            // Une entrée entièrement vide correspond à une ligne que l'on a
            // ajoutée puis laissée de côté : elle ne doit pas être écrite.
            if ($entry !== []) {
                $entries[] = $entry;
            }
        }

        return $entries === [] ? null : $entries;
    }

    /**
     * @return list<string>
     */
    private static function toLines(mixed $value): array
    {
        if (is_array($value)) {
            $value = implode("\n", array_map('strval', $value));
        }
        if (!is_string($value)) {
            return [];
        }

        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $value) ?: [] as $line) {
            $line = self::cleanText($line);
            if ($line !== '') {
                $lines[] = mb_substr($line, 0, 300);
            }
        }

        return $lines;
    }

    /**
     * Normalise un texte saisi : espaces superflus retirés, caractères de
     * contrôle écartés. Le contenu part ensuite dans un fichier JSON puis
     * dans une page, où il sera échappé au moment du rendu.
     */
    private static function cleanText(string $value, bool $keepSpaces = false): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = preg_replace('/[ \t\x{00A0}]+/u', ' ', $value) ?? '';

        // Quelques champs — le suffixe d'un chiffre, par exemple — portent une
        // espace de part et d'autre qui relève de la typographie, pas de la
        // saisie approximative : on ne la rogne que si le schéma le permet.
        return $keepSpaces ? $value : trim($value);
    }

    /**
     * @return array<string,mixed>
     */
    private static function outlineField(): array
    {
        return [
            'type'  => 'number',
            'label' => 'Tracer au trait à partir de la ligne',
            'hint'  => 'Numéro de la première ligne en contour. 1 pour la deuxième ligne, vide pour aucune.',
            'min'   => 0,
            'max'   => 10,
        ];
    }
}
