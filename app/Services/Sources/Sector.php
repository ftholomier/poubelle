<?php
declare(strict_types=1);

namespace App\Services\Sources;

/**
 * Reconnaissance du secteur : spectacle, audiovisuel, événementiel.
 *
 * Aucun agrégateur n'expose de filtre par branche — Jooble, Adzuna et les
 * autres ne comprennent que des mots-clés, et « technicien », « production »
 * ou « montage » ramènent autant d'usines que de plateaux. Le tri se fait donc
 * ici, sur l'intitulé et le résumé de chaque offre remontée.
 *
 * Quatre listes, et une règle :
 *
 *  • FAUX_AMIS — des intitulés d'autres branches qui portent un marqueur :
 *    le « machiniste receveur » conduit un bus, le « régisseur de recettes »
 *    tient une caisse municipale. Ils écartent l'offre sans appel.
 *  • MARQUEURS — un seul suffit. Personne n'écrit « perchman » ou « CDDU »
 *    hors du secteur.
 *  • METIERS — des intitulés que le secteur partage avec d'autres. Ils ne
 *    valent que accompagnés d'un mot de CONTEXTE.
 *  • HORS_SECTEUR — ce qui trahit une autre branche. Écarte l'offre, sauf si
 *    un marqueur franc figure aussi : « technicien de maintenance pour un
 *    théâtre » reste une offre du secteur.
 *
 * Le filtre ne s'applique qu'aux offres externes. Les annonces déposées sur le
 * site ne passent jamais par là : leur pertinence est celle de leur auteur.
 */
final class Sector
{
    /** Homonymes d'autres branches : écartés même en présence d'un marqueur. */
    private const FAUX_AMIS = [
        'machiniste receveur', 'regisseur de recettes', 'regisseur des recettes',
        'regisseur d avances', 'regisseur d avance', 'regisseur comptable',
        'regie de recettes', 'regie d avances', 'operateur de production',
        'plateau technique', 'moissonneuse', 'coiffeur en salon', 'coiffeur salon',
        'coiffure mixte',
    ];

    /** Un seul de ces termes suffit à retenir l'offre. */
    private const MARQUEURS = [
        // statut et cadre d'emploi
        'intermittent', 'intermittence', 'cddu', 'cachet', 'guso', 'audiens',
        'conges spectacles', 'spectacle vivant',
        // métiers sans ambiguïté
        'regisseur', 'regie generale', 'regie plateau', 'machiniste', 'cadreur',
        'perchman', 'perchiste', 'accessoiriste', 'costumier', 'habilleur',
        'eclairagiste', 'sonorisateur', 'bruiteur', 'scenographe', 'scenographie',
        'backliner', 'roadie', 'rigger', 'chef operateur', 'chef machiniste',
        'assistant realisateur', 'realisateur', 'scripte', 'etalonneur',
        'ingenieur du son', 'chef electro', 'technicien plateau', 'projectionniste',
        'video jockey', 'light jockey', 'pupitreur', 'truquiste', 'assistant camera',
        'constructeur de decor', 'monteur de stand', 'standiste', 'perruquier',
        'vfx', 'compositing', 'effets speciaux', 'animateur 2d', 'animateur 3d',
        'metteur en scene', 'metteuse en scene', 'mise en scene', 'choregraphe',
        'administrateur de compagnie', 'tour manager', 'dj', 'disc jockey',
        // plateaux, salles, scènes
        'tournage', 'captation', 'figurant', 'figuration', 'casting', 'plateau tele',
        'comedien', 'danseur', 'danseuse', 'musicien', 'choriste', 'chanteur',
        'chanteuse', 'circassien', 'marionnettiste', 'humoriste', 'artiste de rue',
        'artiste lyrique', 'artiste de cirque', 'acrobate', 'trapeziste', 'jongleur',
        'equilibriste', 'contorsionniste', 'realisatrice', 'costumiere', 'cadreuse',
        // instrumentistes : le nom du pupitre suffit à situer l'offre
        'violoniste', 'violoncelliste', 'pianiste', 'guitariste', 'bassiste',
        'batteur', 'accordeoniste', 'saxophoniste', 'trompettiste', 'flutiste',
        'percussionniste', 'clarinettiste', 'contrebassiste', 'organiste',
        'harpiste', 'altiste', 'corniste', 'instrumentiste',
        // figures de l'animation événementielle
        'pere noel', 'mere noel', 'mascotte', 'echassier', 'magicien',
        'sosie', 'clown', 'conteur', 'conteuse', 'ventriloque',
        // raccourcis du métier
        'ass de prod', 'assistant de prod', 'motion designer', 'motion design',
        'spectacle', 'theatre', 'opera', 'festival', 'concert', 'cabaret',
        'scenique', 'evenementiel', 'audiovisuel', 'sonorisation', 'cirque',
        'plateau de tournage', 'longs metrages', 'court metrage', 'long metrage',
    ];

    /** Utiles seulement en présence d'un mot de CONTEXTE. */
    private const METIERS = [
        'animateur', 'animation', 'technicien', 'monteur', 'montage', 'operateur', 'regie',
        'production', 'producteur', 'assistant', 'coordinateur', 'charge de production',
        'charge de diffusion', 'diffusion', 'maquilleur', 'coiffeur', 'decorateur',
        'decor', 'lumiere', 'son', 'image', 'camera', 'video', 'installateur',
        'stand', 'logistique', 'hote', 'hotesse', 'chef de projet', 'directeur technique',
        'illustrateur', 'photographe', 'cameraman', 'videaste', 'graphiste',
    ];

    /** Ce qui donne son sens à un métier partagé. */
    private const CONTEXTE = [
        'spectacle', 'evenementiel', 'event', 'festival', 'theatre', 'concert',
        'scene', 'plateau', 'tournage', 'film', 'cinema', 'television', 'tele',
        'radio', 'audiovisuel', 'captation', 'salon', 'congres', 'seminaire',
        'mariage', 'soiree', 'animation', 'artistique', 'culturel', 'culture',
        'musique', 'musical', 'danse', 'opera', 'cirque', 'museographie', 'musee',
        'exposition', 'backstage', 'loge', 'sonorisation', 'eclairage', 'lumiere',
        'son', 'video', 'studio', 'scenique', 'noel', 'parc a theme', 'croisiere',
        'colonie', 'centre de loisirs', 'anniversaire', 'kermesse', 'carnaval',
        'defile', 'conference', 'convention', 'gala', 'tournee',
    ];

    /** Signes d'une autre branche. Un marqueur franc annule ce rejet. */
    private const HORS_SECTEUR = [
        'informatique', 'developpeur', 'logiciel', 'reseaux informatiques', 'helpdesk',
        'cybersecurite', 'devops', 'data analyst', 'support technique informatique',
        'industriel', 'usine', 'agroalimentaire', 'chaine de production', 'automaticien',
        'plombier', 'macon', 'soudeur', 'chaudronnier', 'couvreur', 'carreleur',
        'cariste', 'magasinier', 'preparateur de commandes', 'manutentionnaire',
        'aide soignant', 'infirmier', 'medecin', 'pharmacien', 'kinesitherapeute',
        'auxiliaire de vie', 'assistant maternelle',
        'comptable', 'juriste', 'notaire', 'banque', 'assurance', 'credit', 'mutuelle',
        'teleconseiller', 'centre d appels', 'televente', 'commercial sedentaire',
        'caissier', 'boulanger', 'boucher', 'patissier', 'cuisinier', 'plongeur',
        'femme de menage', 'agent d entretien', 'agent de proprete',
        'chauffeur poids lourd', 'chauffeur pl', 'livreur', 'coursier', 'ambulancier',
        'professeur des ecoles', 'educateur specialise', 'agent immobilier',
        'maintenance industrielle', 'technicien de laboratoire', 'technicien informatique',
        'videosurveillance', 'video surveillance', 'videoprotection', 'video protection',
        'alarme', 'salon de coiffure', 'institut de beaute', 'estheticien', 'barbier',
    ];

    /** Vrai si l'offre relève du spectacle, de l'audiovisuel ou de l'événementiel. */
    public static function matches(string ...$parts): bool
    {
        $text = self::normalize(implode(' ', $parts));
        if (trim($text) === '') {
            return false;
        }

        if (self::hit($text, self::FAUX_AMIS)) {
            return false;
        }

        $marqueur = self::hit($text, self::MARQUEURS);

        if (!$marqueur && self::hit($text, self::HORS_SECTEUR)) {
            return false;
        }
        if ($marqueur) {
            return true;
        }
        // Un métier partagé ne vaut qu'accompagné : « technicien » seul ne dit rien.
        return self::hit($text, self::METIERS) && self::hit($text, self::CONTEXTE);
    }

    /**
     * Termes bannis par l'éditeur depuis le back-office, en plus de la liste
     * intégrée. Ils s'appliquent sans exception : c'est un choix explicite.
     *
     * @param string[] $terms
     */
    public static function excluded(array $terms, string ...$parts): bool
    {
        if ($terms === []) {
            return false;
        }
        $clean = [];
        foreach ($terms as $term) {
            $term = trim(self::normalize($term));
            if ($term !== '') {
                $clean[] = $term;
            }
        }
        return $clean !== [] && self::hit(self::normalize(implode(' ', $parts)), $clean);
    }

    /**
     * Accents retirés, ponctuation réduite à des espaces : « Régisseur(se) »,
     * « REGISSEUR » et « régisseur, » deviennent la même chaîne.
     */
    private static function normalize(string $text): string
    {
        $text = mb_strtolower(strip_tags($text), 'UTF-8');
        $text = strtr($text, [
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a','ç'=>'c',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','í'=>'i',
            'ô'=>'o','ö'=>'o','ó'=>'o','õ'=>'o','ù'=>'u','û'=>'u','ü'=>'u',
            'ú'=>'u','ÿ'=>'y','ñ'=>'n','œ'=>'oe','æ'=>'ae','ß'=>'ss',
        ]);
        return ' ' . trim((string) preg_replace('/[^a-z0-9]+/', ' ', $text)) . ' ';
    }

    /**
     * Terminaisons de l'écriture inclusive, que la normalisation détache du
     * mot : « chargé(e) de production » devient « charge e de production ».
     * Elles sont tolérées après chaque mot d'un terme.
     */
    public const INCLUSIF = '(?: (?:[flns]?e|sse|euse|t?rice|ere)s?)?';

    /**
     * Pluriels et féminins sans tout énumérer. Chaque mot d'un terme admet un
     * suffixe — « techniciennes », « monteurs », « événementielle » — et les
     * mots en -eur leurs féminins en -eure, -euse et -rice : « ingénieure »,
     * « régisseuse », « animatrice ».
     *
     * @param string[] $terms
     */
    private static function hit(string $text, array $terms): bool
    {
        static $cache = [];
        $key = md5(implode('|', $terms));
        $cache[$key] ??= '/(?<![a-z])(?:' . implode('|', array_map(
            static fn(string $term): string => implode(' ', array_map(
                static fn(string $word): string => (str_ends_with($word, 'eur') && strlen($word) > 4
                    ? preg_quote(substr($word, 0, -3), '/') . '(?:eur|eure|euse|rice)'
                    : preg_quote($word, '/'))
                    . '(?:s|e|es|le|les|ne|nes|rice|rices|euse|euses|ure|ures)?' . self::INCLUSIF,
                explode(' ', $term),
            )),
            $terms,
        )) . ')(?![a-z])/';

        return preg_match($cache[$key], $text) === 1;
    }
}
