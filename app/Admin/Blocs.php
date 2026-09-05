<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\TexteRiche;

/**
 * Description des blocs de contenu, pour le back-office.
 *
 * Le site public sait rendre une quinzaine de types de blocs
 * (views/partials/bloc.php). Écrire un écran d'édition par type reviendrait à
 * recopier quinze fois le même formulaire ; les décrire une fois ici permet à
 * un seul écran de tous les éditer, et à un type ajouté plus tard d'être
 * éditable sans toucher au formulaire.
 *
 * Chaque champ porte une nature, qui dit comment il se saisit et comment il se
 * relit :
 *
 *   ligne        une ligne de texte
 *   zone         un petit paragraphe
 *   riche        un texte long mis en forme — gras, listes, bouton — filtré
 *                par App\Core\TexteRiche et stocké en HTML
 *   paragraphes  un texte long en clair, découpé aux lignes vides
 *   lignes       une liste, une entrée par ligne
 *   photo        un choix dans la médiathèque
 *   fichier      un chemin de document (PDF), relatif à public/
 *   lien         un couple libellé + adresse
 *   ancre        l'identifiant qui permet de pointer la section
 *   fond         blanc, teinté, sombre, ou l'alternance automatique
 *   icone        un choix parmi les pictogrammes du site
 *   date         un jour, saisi au calendrier du navigateur, stocké en ISO
 *   choix:a|b    une liste fermée
 *   items:<clé>  une liste d'entrées, décrite par SOUS_BLOCS
 */
final class Blocs
{
    /** Pictogrammes proposés, dans l'ordre où ils servent. */
    public const ICONES = [
        'mairie'       => 'Mairie',
        'clocher'      => 'Clocher',
        'foret'        => 'Forêt',
        'document'     => 'Document',
        'etat-civil'   => 'État civil (carte)',
        'elections'    => 'Élections (urne)',
        'urbanisme'    => 'Urbanisme (plan)',
        'internet'     => 'En ligne (globe)',
        'telecharger'  => 'Téléchargement',
        'conseil'      => 'Conseil (table)',
        'budget'       => 'Budget',
        'ecole'        => 'École (cartable)',
        'restauration' => 'Restauration',
        'transport'    => 'Transport (car)',
        'solidarite'   => 'Solidarité',
        'association'  => 'Association',
        'dechets'      => 'Déchets',
        'eau'          => 'Eau',
        'agenda'       => 'Agenda',
        'actualite'    => 'Actualité',
        'telephone'    => 'Téléphone',
        'adresse'      => 'Adresse',
        'horaires'     => 'Horaires',
        'courriel'     => 'Courriel',
        'information'  => 'Information',
        'alerte'       => 'Alerte',
        'urgence'      => 'Urgence',
    ];

    /** @var array<string, array{nom: string, aide: string, champs: array<string, string>}> */
    public const TYPES = [
        'texte' => [
            'nom'  => 'Texte',
            'aide' => 'Un titre, des paragraphes, éventuellement une liste et un lien. Le bloc le plus courant.',
            'champs' => [
                'surtitre'    => 'ligne',
                'titre'       => 'ligne',
                'chapo'       => 'zone',
                'paragraphes' => 'riche',
                'liste'       => 'lignes',
                'lien'        => 'lien',
            ],
        ],
        'duo' => [
            'nom'  => 'Image et texte',
            'aide' => 'Une photo et un texte côte à côte. Les points numérotés rythment la colonne de droite.',
            'champs' => [
                'surtitre'    => 'ligne',
                'titre'       => 'ligne',
                'image'       => 'photo',
                'image_alt'   => 'ligne',
                'sens'        => 'choix:|image-droite',
                'cadrage'     => 'choix:|portrait',
                'paragraphes' => 'riche',
                'liste'       => 'lignes',
                'points'      => 'items:points',
                'lien'        => 'lien',
            ],
        ],
        'cartes' => [
            'nom'  => 'Cartes de rubrique',
            'aide' => 'Une grille de cartes avec pictogramme, titre, résumé et lien. Trois par rangée.',
            'champs' => [
                'surtitre' => 'ligne',
                'titre'    => 'ligne',
                'chapo'    => 'zone',
                'items'    => 'items:cartes',
            ],
        ],
        'liens' => [
            'nom'  => 'Liste de liens',
            'aide' => 'Des liens internes ou sortants, chacun avec une ligne d’explication. Les liens sortants sont signalés d’eux-mêmes.',
            'champs' => [
                'surtitre' => 'ligne',
                'titre'    => 'ligne',
                'chapo'    => 'zone',
                'items'    => 'items:liens',
            ],
        ],
        'contacts' => [
            'nom'  => 'Fiches de contact',
            'aide' => 'Un organisme par fiche : adresse, téléphone, courriel, site.',
            'champs' => [
                'surtitre' => 'ligne',
                'titre'    => 'ligne',
                'chapo'    => 'zone',
                'items'    => 'items:contacts',
            ],
        ],
        'documents' => [
            'nom'  => 'Documents à télécharger',
            'aide' => 'Des PDF déposés dans public/assets/doc/. Le poids est calculé à l’affichage.',
            'champs' => [
                'titre' => 'ligne',
                'chapo' => 'zone',
                'items' => 'items:documents',
            ],
        ],
        'etapes' => [
            'nom'  => 'Étapes numérotées',
            'aide' => 'Une marche à suivre. Les numéros sont posés automatiquement.',
            'champs' => [
                'surtitre' => 'ligne',
                'titre'    => 'ligne',
                'chapo'    => 'zone',
                'items'    => 'items:etapes',
            ],
        ],
        'tableau' => [
            'nom'  => 'Tableau',
            'aide' => 'Des horaires, des tarifs. Une valeur par ligne dans la colonne de droite.',
            'champs' => [
                'titre'   => 'ligne',
                'chapo'   => 'zone',
                'entetes' => 'lignes',
                'lignes'  => 'items:tableau',
            ],
        ],
        'encadre' => [
            'nom'  => 'Encadré',
            'aide' => 'Une information à ne pas manquer. Le ton « alerte » réserve le picto d’avertissement.',
            'champs' => [
                'intitule'    => 'ligne',
                'ton'         => 'choix:info|alerte',
                'paragraphes' => 'riche',
                'lien'        => 'lien',
            ],
        ],
        /* L'hébergeur est un bloc à champs, et non un paragraphe libre, pour
           une raison de droit : l'article 6-III de la LCEN impose de PUBLIER
           son nom, son adresse et son téléphone. Écrit en prose, le
           renseignement se perd — le socle affirmait « coordonnées
           disponibles sur demande auprès du secrétariat », ce qui ne suffit
           pas. En champs, le tableau de bord sait dire qu'ils manquent. */
        'hebergeur' => [
            'nom'  => 'Hébergeur du site',
            'aide' => 'Obligatoire sur les mentions légales : nom, adresse et téléphone de '
                    . 'l’entreprise qui héberge le site. Votre prestataire vous les donne.',
            'champs' => [
                'titre'     => 'ligne',
                'raison'    => 'ligne',
                'adresse'   => 'zone',
                'telephone' => 'ligne',
                'site'      => 'ligne',
            ],
        ],
        'citation' => [
            'nom'  => 'Citation',
            'aide' => 'Une phrase seule, sur fond sombre.',
            'champs' => [
                'texte'  => 'zone',
                'auteur' => 'ligne',
            ],
        ],
        'chiffres' => [
            'nom'  => 'Chiffres clés',
            'aide' => 'Quatre chiffres en bande. La valeur et son unité sont séparées pour que l’unité soit composée plus petite.',
            'champs' => [
                'titre' => 'ligne',
                'items' => 'items:chiffres',
            ],
        ],
        'photo' => [
            'nom'  => 'Photo pleine largeur',
            'aide' => 'Une photo en bandeau, avec sa légende.',
            'champs' => [
                'image'     => 'photo',
                'image_alt' => 'ligne',
                'legende'   => 'zone',
            ],
        ],
    ];

    /** @var array<string, array<string, string>> */
    public const SOUS_BLOCS = [
        'cartes' => [
            'icone'        => 'icone',
            'titre'        => 'ligne',
            'texte'        => 'zone',
            'lien.libelle' => 'ligne',
            'lien.url'     => 'ligne',
        ],
        'liens' => [
            'titre' => 'ligne',
            'texte' => 'zone',
            'url'   => 'ligne',
        ],
        'contacts' => [
            'nom'     => 'ligne',
            'role'    => 'ligne',
            'texte'   => 'zone',
            'adresse' => 'zone',
            'tel'     => 'ligne',
            'email'   => 'ligne',
            'site'    => 'ligne',
        ],
        'documents' => [
            'titre'   => 'ligne',
            'texte'   => 'zone',
            'date'    => 'date',
            'fichier' => 'fichier',
        ],
        'etapes' => [
            'titre' => 'ligne',
            'texte' => 'zone',
        ],
        'points' => [
            'numero' => 'ligne',
            'titre'  => 'ligne',
            'texte'  => 'zone',
        ],
        'chiffres' => [
            'valeur'  => 'ligne',
            'unite'   => 'ligne',
            'libelle' => 'ligne',
        ],
        'tableau' => [
            'libelle' => 'ligne',
            'valeurs' => 'lignes',
        ],
    ];

    /** Intitulés des champs, pour ne pas afficher « image_alt » à un élu. */
    public const LIBELLES = [
        'surtitre'     => 'Sur-titre',
        'titre'        => 'Titre',
        'intitule'     => 'Titre',
        'chapo'        => 'Chapô',
        'paragraphes'  => 'Texte',
        'liste'        => 'Liste à puces',
        'lien'         => 'Lien',
        'image'        => 'Photo',
        'image_alt'    => 'Description de la photo',
        'legende'      => 'Légende',
        'sens'         => 'Côté de la photo',
        'cadrage'      => 'Cadrage',
        'points'       => 'Points numérotés',
        'items'        => 'Entrées',
        'lignes'       => 'Lignes du tableau',
        'entetes'      => 'En-têtes de colonnes',
        'ton'          => 'Ton',
        'texte'        => 'Texte',
        'auteur'       => 'Auteur',
        'nom'          => 'Nom',
        'role'         => 'Rôle',
        'adresse'      => 'Adresse',
        'tel'          => 'Téléphone',
        'email'        => 'Courriel',
        'site'         => 'Site internet',
        'url'          => 'Adresse',
        'lien.libelle' => 'Libellé du lien',
        'lien.url'     => 'Adresse du lien',
        'icone'        => 'Pictogramme',
        'date'         => 'Date',
        'fichier'      => 'Fichier',
        'valeur'       => 'Valeur',
        'unite'        => 'Unité',
        'libelle'      => 'Libellé',
        'valeurs'      => 'Valeurs',
        'numero'       => 'Numéro',
        'id'           => 'Ancre',
        'fond'         => 'Fond',
    ];

    /** Valeurs proposées par les listes fermées. */
    public const CHOIX = [
        'sens'    => ['' => 'Photo à gauche', 'image-droite' => 'Photo à droite'],
        'cadrage' => ['' => 'Paysage (4/3)', 'portrait' => 'Portrait (4/5)'],
        'ton'     => ['info' => 'Information', 'alerte' => 'Alerte'],
        'fond'    => ['' => 'Alternance automatique', 'blanc' => 'Blanc',
                      'teinte' => 'Teinté', 'sombre' => 'Sombre'],
    ];

    public static function libelle(string $champ): string
    {
        return self::LIBELLES[$champ] ?? ucfirst(str_replace('_', ' ', $champ));
    }

    /**
     * Relit un bloc depuis les données du formulaire.
     *
     * Les champs vides ne sont pas écrits : un JSON encombré de clés vides est
     * plus difficile à relire, et le gabarit teste la présence, pas la valeur.
     *
     * @param array<string, mixed> $brut
     * @return array<string, mixed>|null null si le bloc est entièrement vide
     */
    public static function relire(array $brut): ?array
    {
        $type = (string) ($brut['type'] ?? '');
        if (!isset(self::TYPES[$type])) {
            return null;
        }

        $bloc = ['type' => $type];
        foreach (['id', 'fond'] as $commun) {
            $valeur = trim((string) ($brut[$commun] ?? ''));
            if ($valeur !== '') {
                $bloc[$commun] = $valeur;
            }
        }

        foreach (self::TYPES[$type]['champs'] as $champ => $nature) {
            $valeur = self::relireChamp($nature, $brut[$champ] ?? null);
            if ($valeur !== null) {
                $bloc[$champ] = $valeur;
            }
        }

        // Un bloc réduit à son type n'a rien à faire dans le contenu : c'est un
        // bloc ajouté puis laissé vide, et il produirait une section blanche.
        return count($bloc) > 1 ? $bloc : null;
    }

    /**
     * Une date au format ISO, ou la chaîne vide.
     *
     * Le champ du navigateur renvoie déjà AAAA-MM-JJ, mais rien ne garantit
     * qu'on passe par lui : un vieux navigateur rend un champ texte libre, et
     * le contenu peut aussi arriver par l'éditeur JSON. Une date fantaisiste
     * enregistrée telle quelle ne casserait rien à l'affichage — date_texte()
     * la laisserait passer — mais elle fausserait le tri de l'agenda et des
     * actualités, qui compare les chaînes. On préfère perdre la saisie que
     * ranger un rendez-vous au mauvais endroit.
     *
     * checkdate() en plus du format : « 2026-02-31 » a la bonne forme et
     * n'existe pas.
     */
    public static function jour(string $brut): string
    {
        $valeur = trim($brut);
        if ($valeur === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valeur, $m)) {
            return '';
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $valeur : '';
    }

    /**
     * La nature du champ impose-t-elle une valeur même sans saisie ?
     *
     * Un menu déroulant rend toujours une option, une case rend son état :
     * leur présence ne prouve donc pas qu'on a rempli la ligne.
     */
    public static function estImposee(string $nature): bool
    {
        return $nature === 'icone' || $nature === 'case' || str_starts_with($nature, 'choix:');
    }

    /**
     * Relit un champ selon sa nature.
     *
     * Publique et paramétrable parce que les listes du back-office s'en
     * servent aussi. Elles avaient leur propre copie, qui a dérivé : elle
     * ignorait les natures « lien », « riche » et « date » ajoutées depuis, si
     * bien qu'une date impossible saisie dans l'agenda était enregistrée telle
     * quelle. Deux relectures pour un même schéma de champs, c'est toujours la
     * moins tenue qui décide.
     *
     * @param array<string, array<string, string>> $sousSchemas table des
     *        sous-blocs à utiliser pour les natures « items: » — celle des
     *        blocs de contenu par défaut, celle des listes quand on les relit
     */
    public static function relireChamp(string $nature, mixed $brut, ?array $sousSchemas = null): mixed
    {
        $sousSchemas ??= self::SOUS_BLOCS;

        // Une <textarea> renvoie ses fins de ligne en CRLF, c'est la norme
        // HTML. Stockées telles quelles, elles font grossir le JSON d'un \r
        // par ligne à chaque enregistrement et rendent tout aller-retour
        // « modifié » alors que rien ne l'est — on ne voit plus les vraies
        // différences dans un diff.
        if (is_string($brut)) {
            $brut = str_replace(["\r\n", "\r"], "\n", $brut);
        }

        if (str_starts_with($nature, 'items:')) {
            $sous = $sousSchemas[substr($nature, 6)] ?? [];
            $entrees = [];
            foreach ((array) $brut as $ligne) {
                if (!is_array($ligne)) {
                    continue;
                }
                $entree = [];
                // Une entrée n'existe que si l'on y a saisi quelque chose. Les
                // menus déroulants ne comptent pas : un <select> renvoie
                // toujours sa première option, donc les deux lignes vides que
                // le formulaire garde en réserve arrivaient ici avec une icône
                // et étaient enregistrées. Deux cartes fantômes de plus à
                // chaque enregistrement, et une page qui se remplit de blocs
                // vides sans que personne n'ait rien ajouté.
                $saisie = false;
                foreach ($sous as $champ => $sousNature) {
                    $valeur = self::relireChamp($sousNature, self::extraire($ligne, $champ), $sousSchemas);
                    if ($valeur === null) {
                        continue;
                    }
                    self::poser($entree, $champ, $valeur);
                    if (!self::estImposee($sousNature)) {
                        $saisie = true;
                    }
                }
                if ($saisie) {
                    $entrees[] = $entree;
                }
            }
            return $entrees !== [] ? $entrees : null;
        }

        if ($nature === 'lien') {
            $libelle = trim((string) (is_array($brut) ? ($brut['libelle'] ?? '') : ''));
            $url     = trim((string) (is_array($brut) ? ($brut['url'] ?? '') : ''));
            return $url !== '' ? ['libelle' => $libelle, 'url' => $url] : null;
        }

        if ($nature === 'date') {
            $jour = self::jour((string) $brut);
            return $jour !== '' ? $jour : null;
        }

        if ($nature === 'riche') {
            // Le filtrage est refait à l'affichage : ici il sert à ce que le
            // JSON reste lisible et honnête, pas de garde-fou — un contenu
            // arrivé par l'éditeur avancé ne passe jamais par cette fonction.
            $html = TexteRiche::nettoyer((string) $brut);
            return $html !== '' ? $html : null;
        }

        if ($nature === 'paragraphes') {
            $blocs = preg_split('/\R{2,}/u', trim((string) $brut)) ?: [];
            $blocs = array_values(array_filter(array_map(
                static fn(string $p): string => trim(preg_replace('/\R+/u', ' ', $p) ?? ''),
                $blocs
            )));
            return $blocs !== [] ? $blocs : null;
        }

        if ($nature === 'lignes') {
            $lignes = preg_split('/\R/u', trim((string) $brut)) ?: [];
            $lignes = array_values(array_filter(array_map('trim', $lignes)));
            return $lignes !== [] ? $lignes : null;
        }

        $valeur = trim((string) $brut);

        if (($nature === 'photo' || $nature === 'fichier') && $valeur !== '') {
            return self::cheminAsset($valeur);
        }

        return $valeur !== '' ? $valeur : null;
    }

    /**
     * Un chemin de fichier servi par le site, ou rien.
     *
     * Ces champs sont des listes déroulantes à l'écran, mais rien n'empêche
     * d'en poster autre chose : une adresse « javascript: » deviendrait le
     * `href` d'un bouton de téléchargement. On exige donc un chemin relatif
     * sous `assets/`, sans protocole et sans remontée de dossier.
     *
     * **Et non `Mediatheque::existe()`**, qui serait la réponse évidente : la
     * médiathèque ne connaît que `assets/img/site`, alors que `fichier` porte
     * des PDF rangés dans `assets/doc`. Valider par elle effacerait en silence
     * le fichier de chaque document à l'enregistrement — précisément le genre
     * de perte que ce dépôt cherche à éviter.
     */
    private static function cheminAsset(string $valeur): ?string
    {
        $propre = ltrim($valeur, '/');
        if (!str_starts_with($propre, 'assets/')
            || str_contains($propre, '..')
            || preg_match('~^[a-z][a-z0-9+.-]*:~i', $propre) === 1) {
            return null;
        }

        return $propre;
    }

    /** Lit `lien.libelle` dans un tableau imbriqué. */
    private static function extraire(array $source, string $chemin): mixed
    {
        foreach (explode('.', $chemin) as $segment) {
            if (!is_array($source) || !array_key_exists($segment, $source)) {
                return null;
            }
            $source = $source[$segment];
        }
        return $source;
    }

    /** Écrit `lien.libelle` dans un tableau imbriqué. */
    private static function poser(array &$cible, string $chemin, mixed $valeur): void
    {
        $segments = explode('.', $chemin);
        $dernier  = array_pop($segments);
        $courant  = &$cible;
        foreach ($segments as $segment) {
            if (!isset($courant[$segment]) || !is_array($courant[$segment])) {
                $courant[$segment] = [];
            }
            $courant = &$courant[$segment];
        }
        $courant[$dernier] = $valeur;
    }
}
