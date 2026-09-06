<?php
declare(strict_types=1);

/**
 * Le contenu écrit et le schéma du code disent-ils encore la même chose ?
 *
 * **Pourquoi ce script existe.** Le schéma vit dans `App\Admin\Blocs::TYPES`,
 * le contenu vit dans des fichiers JSON. Les deux évoluent séparément, et la
 * relation entre eux est asymétrique d'une façon qui se retourne contre le
 * client : la LECTURE est tolérante — un champ manquant se rend en chaîne vide
 * — tandis que l'ÉCRITURE est destructive, `Blocs::relire()` reconstruisant
 * chaque bloc à partir du schéma courant et jetant tout ce qu'il ne nomme
 * plus.
 *
 * Renommer un champ ne casse donc rien tout de suite. Le contenu s'affiche, le
 * site a l'air correct, et la valeur de la mairie disparaît le jour où elle
 * enregistre cet écran — des semaines plus tard, sans message, et sans que
 * personne puisse relier la perte à la livraison.
 *
 * `App\Core\Migrations` sait transformer le contenu ancien. Mais une migration
 * ne s'écrit pas d'elle-même : renommer un champ en oubliant l'étape laisse le
 * mécanisme muet. **Ce script est la moitié qui constate.** Il confronte tout
 * le contenu au schéma et refuse trois situations :
 *
 *   · un bloc dont le TYPE n'existe plus dans Blocs::TYPES — il disparaîtra
 *     de la page au premier enregistrement ;
 *   · un CHAMP que son type ne déclare plus — il sera vidé de même ;
 *   · un contenu dont le `_version` est en retard alors qu'aucune étape ne
 *     couvre l'écart, ou en avance sur le code.
 *
 * Il ne pilote aucun navigateur et ne demande aucun serveur : il lit des
 * fichiers et une constante. Deux secondes.
 *
 * Usage :
 *     php outils/verifs/schema.php
 *     php outils/verifs/schema.php --data      # aussi le contenu vivant de data/
 *
 * Sort en code 1 s'il trouve quelque chose.
 */

$racine = dirname(__DIR__, 2);
require $racine . '/app/bootstrap.php';

use App\Admin\Blocs;
use App\Core\Adresse;
use App\Core\Migrations;

$avecData = in_array('--data', $argv, true);

/* Les clés qu'un bloc porte sans que son type ait à les déclarer : elles sont
   communes à tous et relues par Blocs::relire() hors de la boucle des champs. */
const COMMUNES = ['type', 'id', 'fond'];

/**
 * Les fichiers à examiner, et sous quel nom de contenu.
 *
 * @return array<string, string> chemin absolu => nom du contenu
 */
function fichiers(string $racine, bool $avecData): array
{
    $liste = [];
    foreach (['data-modele', 'data'] as $base) {
        if ($base === 'data' && !$avecData) {
            continue;
        }
        foreach ([$base . '/*.json', $base . '/pages/*.json'] as $motif) {
            foreach (glob($racine . '/' . $motif) ?: [] as $chemin) {
                $nom = substr($chemin, strlen($racine . '/' . $base . '/'), -5);
                $liste[$chemin] = $nom;
            }
        }
    }

    return $liste;
}

/**
 * Les écarts d'un fichier. Rend la liste des phrases à afficher.
 *
 * @return string[]
 */
function ecartsDuFichier(string $chemin, string $nom): array
{
    $brut = file_get_contents($chemin);
    if ($brut === false) {
        return ['illisible'];
    }
    $donnees = json_decode($brut, true);
    if (!is_array($donnees)) {
        return ['JSON invalide : ' . json_last_error_msg()];
    }

    $ecarts = [];

    // --- la version -------------------------------------------------------
    $version = Migrations::version($donnees);
    if (Migrations::enAvance($donnees)) {
        $ecarts[] = sprintf(
            'version %d, alors que le code est en %d : ce contenu vient d’un code plus récent',
            $version,
            Migrations::VERSION
        );
    } elseif ($version < Migrations::VERSION && str_starts_with($chemin, dirname(__DIR__, 2) . '/data-modele/')) {
        // Un fichier du modèle en retard est une négligence du dépôt, pas un
        // contenu de client : il se corrige d'une ligne, ici et maintenant.
        $ecarts[] = sprintf(
            'version %d dans data-modele/ : le modèle livré doit partir à la version %d',
            $version,
            Migrations::VERSION
        );
    }

    // --- les blocs --------------------------------------------------------
    foreach ((array) ($donnees['sections'] ?? []) as $rang => $bloc) {
        if (!is_array($bloc)) {
            continue;
        }
        $type = (string) ($bloc['type'] ?? '');

        if (!isset(Blocs::TYPES[$type])) {
            $ecarts[] = sprintf(
                'bloc %d de type « %s » inconnu du code : il DISPARAÎTRA au premier '
                . 'enregistrement de cette page. Écrire une étape de migration, ou '
                . 'rétablir le type.',
                $rang,
                $type !== '' ? $type : '(sans type)'
            );
            continue;
        }

        $declares = array_keys(Blocs::TYPES[$type]['champs']);
        foreach (array_keys($bloc) as $champ) {
            if (in_array($champ, COMMUNES, true) || in_array($champ, $declares, true)) {
                continue;
            }
            $ecarts[] = sprintf(
                'bloc %d (%s) : le champ « %s » n’est plus déclaré par ce type, sa '
                . 'valeur sera JETÉE au premier enregistrement. Écrire une étape de '
                . 'migration qui la déplace, ou redéclarer le champ.',
                $rang,
                $type,
                $champ
            );
        }

        /* Les adresses déjà écrites survivent-elles au filtre ?
           Depuis que les champs d'adresse passent par App\Core\Adresse, une
           adresse que le filtre refuse sera VIDÉE au premier enregistrement —
           silencieusement, comme un champ renommé. Le cas n'est pas théorique :
           une adresse saisie sans « https:// », ou avec un espace, était
           acceptée avant et ne l'est plus. Le signaler ici, c'est le corriger
           avant la livraison plutôt qu'après la perte. */
        foreach (adressesDuBloc($type, $bloc) as $chemin => $valeur) {
            $propre = Adresse::nettoyer($valeur, true);
            if ($propre !== $valeur) {
                $ecarts[] = sprintf(
                    'bloc %d (%s) : l’adresse « %s » de %s %s au premier enregistrement '
                    . '(le filtre en rendrait « %s »). Corriger la saisie.',
                    $rang,
                    $type,
                    $valeur,
                    $chemin,
                    $propre === '' ? 'sera VIDÉE' : 'sera RÉÉCRITE',
                    $propre
                );
            }
        }
    }

    return $ecarts;
}

/**
 * Les adresses portées par un bloc, avec le chemin où les retrouver.
 *
 * Le schéma dit lesquelles : la nature « url », et le sous-champ `url` de la
 * nature « lien ». Chercher par nom de champ aurait raté `lien.url` et pris
 * `image`, qui n'est pas une adresse mais un nom de fichier.
 *
 * @return array<string, string> chemin lisible => adresse
 */
function adressesDuBloc(string $type, array $bloc): array
{
    $trouvees = [];

    foreach (Blocs::TYPES[$type]['champs'] as $champ => $nature) {
        $valeur = $bloc[$champ] ?? null;

        if ($nature === 'url' && is_string($valeur) && $valeur !== '') {
            $trouvees[$champ] = $valeur;
        }
        if ($nature === 'lien' && is_array($valeur) && ($valeur['url'] ?? '') !== '') {
            $trouvees[$champ . '.url'] = (string) $valeur['url'];
        }
        if (str_starts_with($nature, 'items:') && is_array($valeur)) {
            $sous = Blocs::SOUS_BLOCS[substr($nature, 6)] ?? [];
            foreach ($valeur as $rang => $entree) {
                if (!is_array($entree)) {
                    continue;
                }
                foreach ($sous as $sousChamp => $sousNature) {
                    if ($sousNature !== 'url') {
                        continue;
                    }
                    // « lien.url » est un chemin dans l'entrée, pas une clé.
                    $lu = $entree;
                    foreach (explode('.', $sousChamp) as $morceau) {
                        $lu = is_array($lu) ? ($lu[$morceau] ?? null) : null;
                    }
                    if (is_string($lu) && $lu !== '') {
                        $trouvees[$champ . '[' . $rang . '].' . $sousChamp] = $lu;
                    }
                }
            }
        }
    }

    return $trouvees;
}

// ---------------------------------------------------------------------- main

$fichiers = fichiers($racine, $avecData);
$total = 0;

foreach ($fichiers as $chemin => $nom) {
    $ecarts = ecartsDuFichier($chemin, $nom);
    $total += count($ecarts);

    printf("  %-42s %s\n", $nom, $ecarts === [] ? 'ok' : count($ecarts) . ' écart(s)');
    foreach ($ecarts as $e) {
        echo '        · ' . $e . "\n";
    }
}

echo "---\n";
printf(
    "%d fichier(s) confronté(s) au schéma, version %d — %d écart(s).\n",
    count($fichiers),
    Migrations::VERSION,
    $total
);

if (!$avecData) {
    echo "Le contenu vivant de data/ n’a pas été lu : relancez avec --data pour l’inclure.\n";
}

exit($total > 0 ? 1 : 0);
