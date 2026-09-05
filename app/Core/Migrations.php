<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Fait suivre le contenu déjà écrit quand la forme attendue change.
 *
 * **Le problème que cette classe résout.** Le schéma de ce site vit dans le
 * code — `App\Admin\Blocs::TYPES` —, et le contenu vit dans des fichiers JSON
 * chez le client. Les deux évoluent séparément. Or la lecture est tolérante
 * (un champ manquant se rend en chaîne vide) tandis que **l'écriture est
 * destructive** : `Blocs::relire()` reconstruit chaque bloc à partir du schéma
 * courant, et jette tout ce que ce schéma ne nomme plus. Renommer un champ
 * suffit donc à vider la valeur de la mairie — pas au moment de la mise à
 * jour, mais au premier enregistrement qu'elle fera ensuite. Silencieusement,
 * et sans que personne puisse relier la perte à la livraison.
 *
 * **Ce que cette classe fait.** Chaque fichier de contenu porte un `_version`.
 * À la lecture, les étapes manquantes sont appliquées dans l'ordre et le
 * fichier est réécrit aussitôt : le disque est toujours à la version courante,
 * et une étape ne se rejoue jamais.
 *
 * **Ce qu'elle ne fait pas, et pourquoi l'auditeur existe.** Une migration ne
 * s'écrit pas d'elle-même. Renommer un champ dans `Blocs::TYPES` sans écrire
 * l'étape correspondante laisse le mécanisme muet, et l'on se retrouve
 * exactement là où l'on était. C'est `outils/verifs/schema.php` qui refuse ce
 * cas : il confronte le contenu au schéma et crie qu'il manque une étape.
 * Les deux vont ensemble ; l'un transforme, l'autre constate.
 *
 * **Écrire une étape.** Ajoutez une entrée à ETAPES, numérotée par la version
 * qu'elle produit, et incrémentez VERSION. Une étape reçoit le nom du contenu
 * et son tableau, et rend le tableau transformé. Elle doit être **prudente** :
 * ne transformer que ce qu'elle reconnaît avec certitude, et laisser le reste
 * intact. Une migration qui écrase du texte écrit par la mairie est pire que
 * l'absence de migration.
 */
final class Migrations
{
    /**
     * La version que ce code sait lire et écrire.
     *
     * Un fichier sans `_version` est réputé être à la version 1 : c'est la
     * forme qu'avait le contenu le jour où ce mécanisme est né, et il n'y a
     * pas de moyen de distinguer « ancien » de « n'a jamais eu de numéro ».
     */
    public const VERSION = 2;

    /**
     * Les étapes, dans l'ordre. La clé est la version PRODUITE par l'étape.
     *
     * @var array<int, string>
     */
    private const ETAPES = [
        2 => 'versDeux',
    ];

    /** La version d'un contenu lu. */
    public static function version(array $donnees): int
    {
        $v = $donnees['_version'] ?? 1;

        return is_int($v) && $v > 0 ? $v : 1;
    }

    /**
     * Le contenu vient-il d'un code plus récent que celui qui tourne ?
     *
     * Cela arrive pour de bon : un retour en arrière du code après un
     * déploiement raté, ou la restauration d'une sauvegarde prise plus tard.
     * On ne touche alors à rien — le migrer à l'envers reviendrait à inventer
     * des étapes descendantes que personne n'a écrites — et le tableau de bord
     * le signale.
     */
    public static function enAvance(array $donnees): bool
    {
        return self::version($donnees) > self::VERSION;
    }

    /**
     * Applique les étapes manquantes.
     *
     * Rend `null` quand il n'y avait rien à faire : l'appelant sait alors
     * qu'il n'a pas à réécrire le fichier, et une lecture reste une lecture.
     *
     * @param array<mixed> $donnees
     * @return array<mixed>|null
     */
    public static function appliquer(string $nom, array $donnees): ?array
    {
        $depuis = self::version($donnees);
        if ($depuis >= self::VERSION) {
            return null;
        }

        foreach (self::ETAPES as $vers => $methode) {
            if ($vers > $depuis) {
                $donnees = self::$methode($nom, $donnees);
            }
        }
        $donnees['_version'] = self::VERSION;

        return $donnees;
    }

    // --------------------------------------------------------------- étapes

    /**
     * Version 2 — l'hébergement des mentions légales devient un bloc à champs.
     *
     * L'article 6-III de la LCEN impose de publier la dénomination, l'adresse
     * et le téléphone de l'hébergeur. Le socle livrait un paragraphe qui
     * disait « coordonnées disponibles sur demande auprès du secrétariat », ce
     * qui ne suffit pas ; le bloc `hebergeur` a remplacé ce paragraphe pour
     * que le tableau de bord puisse réclamer les champs tant qu'ils sont vides.
     *
     * **La prudence de cette étape est son sujet principal.** Une mairie a pu
     * remplacer ce paragraphe par un vrai texte d'hébergement, écrit à la
     * main. Le convertir en bloc à champs reviendrait à jeter ce texte. On ne
     * convertit donc QUE si le paragraphe est resté celui du socle, mot pour
     * mot ; sinon on laisse la section telle quelle, et l'auditeur de schéma
     * la signalera comme un bloc `texte` de plus — ce qu'elle est légitimement.
     *
     * @param array<mixed> $donnees
     * @return array<mixed>
     */
    private static function versDeux(string $nom, array $donnees): array
    {
        if ($nom !== 'pages/mentions-legales' || !isset($donnees['sections'])) {
            return $donnees;
        }

        // Le paragraphe tel que le socle le livrait, réduit à ses mots : la
        // ponctuation et les espaces changent d'un enregistrement à l'autre.
        $empreinteSocle = self::mots(
            'Le site est hébergé en France. Les coordonnées de l’hébergeur sont '
            . 'disponibles sur demande auprès du secrétariat de mairie.'
        );

        foreach ((array) $donnees['sections'] as $rang => $section) {
            if (!is_array($section) || ($section['type'] ?? '') !== 'texte') {
                continue;
            }
            if (self::mots((string) ($section['paragraphes'] ?? '')) !== $empreinteSocle) {
                continue;
            }

            $donnees['sections'][$rang] = [
                'type'      => 'hebergeur',
                'titre'     => (string) ($section['titre'] ?? 'Hébergement'),
                'raison'    => '',
                'adresse'   => '',
                'telephone' => '',
                'site'      => '',
            ];
        }

        return $donnees;
    }

    /** Un texte réduit à ses mots, pour comparer sans buter sur la mise en forme. */
    private static function mots(string $valeur): string
    {
        $texte = html_entity_decode(strip_tags($valeur), ENT_QUOTES, 'UTF-8');
        $texte = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $texte) ?? $texte;

        return mb_strtolower(trim($texte));
    }
}
