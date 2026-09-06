<?php
declare(strict_types=1);

/**
 * Ce que le serveur web laisserait atteindre s'il servait la racine du dépôt.
 *
 * **Pourquoi ce script existe.** `DEPLOIEMENT.md` décrit deux implantations.
 * La bonne fait pointer la racine web sur `public/`, et rien d'autre n'est
 * atteignable. L'autre — celle des hébergements mutualisés qui imposent
 * `public_html` — pose la racine du dépôt en racine web : `data/`, `storage/`
 * et `outils/` deviennent alors des dossiers publics, et seuls leurs
 * `.htaccess` les ferment.
 *
 * Ces fichiers étaient déclarés dans `Deploiement::CODE` et vantés par la
 * documentation, mais trois d'entre eux N'EXISTAIENT PAS. Étaient donc
 * téléchargeables, dans cette implantation : `data/admin/parametres.json`
 * (empreinte du mot de passe d'administration, mot de passe SMTP, jetons
 * Meta, clé de l'assistant), les conversations des administrés avec leurs
 * coordonnées, et les archives de `storage/deploiements/`, qui contiennent
 * une copie de `data/admin/`.
 *
 * Rien de tout cela ne se voit dans une page : un `.htaccess` absent ne
 * produit aucune erreur, la mairie ne voit rien, et le site fonctionne
 * exactement pareil. C'est la règle de `CLAUDE.md` — ce qui ne se voit pas
 * dans la page doit être mesuré là où il se voit — appliquée au système de
 * fichiers.
 *
 * Trois contrôles :
 *
 *   1. **tout dossier du dépôt sauf `public/`** porte un refus valable pour
 *      tout le dossier. Cette formulation vaut mieux qu'une liste : un
 *      dossier ajouté demain est couvert sans que personne y pense ;
 *   2. chaque `.htaccess` déclaré dans `Deploiement::CODE` existe — sans quoi
 *      une mise à jour ne le rétablirait pas sur un site déjà en ligne ;
 *   3. chaque dossier de `Permissions::secrets()` est couvert par un refus,
 *      posé sur lui-même ou sur l'un de ses parents.
 *
 * Un refus n'est retenu que s'il vaut pour le dossier entier : `public/`
 * contient bien `Require all denied`, mais à l'intérieur d'un `<FilesMatch>`
 * qui ne vise que les `.json` et les `.md`. Prendre ce refus pour un refus
 * global ferait déclarer conforme un dossier entièrement ouvert.
 *
 * Il ne pilote aucun navigateur et ne demande aucun serveur. Deux secondes.
 *
 * Usage :
 *     php outils/verifs/exposition.php
 *
 * Sort en code 1 s'il trouve quelque chose.
 */

$racine = dirname(__DIR__, 2);
require $racine . '/app/bootstrap.php';

use App\Core\Deploiement;
use App\Core\Permissions;

/** Dossiers du dépôt qui n'ont pas à être fermés. */
const OUVERTS = ['public'];

/** Jamais versionnés, jamais présents sur le serveur. */
const HORS_SUJET = ['.git', 'node_modules', 'vendor'];

/**
 * Le dossier refuse-t-il l'accès à la totalité de son contenu ?
 *
 * Les blocs `<Files>`, `<FilesMatch>`, `<Directory>` et `<Location>` sont
 * retirés avant la recherche : un refus qui n'y vise qu'une extension ne
 * ferme pas le dossier.
 */
function refusGlobal(string $htaccess): bool
{
    if (!is_file($htaccess)) {
        return false;
    }
    $texte = (string) file_get_contents($htaccess);

    // Les commentaires d'abord : ce fichier en contient qui citent la règle.
    $texte = preg_replace('/^\s*#.*$/m', '', $texte) ?? $texte;
    $texte = preg_replace('~<(Files|FilesMatch|Directory|Location)[^>]*>.*?</\1>~is', '', $texte) ?? $texte;

    return preg_match('/^\s*Require\s+all\s+denied\s*$/mi', $texte) === 1
        || preg_match('/^\s*Deny\s+from\s+all\s*$/mi', $texte) === 1;
}

/** Le dossier, ou l'un de ses parents jusqu'à la racine, pose-t-il un refus ? */
function couvert(string $racine, string $relatif): bool
{
    $morceaux = explode('/', trim($relatif, '/'));
    while ($morceaux !== []) {
        if (refusGlobal($racine . '/' . implode('/', $morceaux) . '/.htaccess')) {
            return true;
        }
        array_pop($morceaux);
    }
    return false;
}

// ---------------------------------------------------------------------- main

$ecarts = [];

// 1. tout dossier du dépôt sauf public/
$dossiers = [];
foreach ((array) scandir($racine) as $entree) {
    if ($entree === '.' || $entree === '..' || !is_dir($racine . '/' . $entree)) {
        continue;
    }
    if (in_array($entree, HORS_SUJET, true) || in_array($entree, OUVERTS, true)) {
        continue;
    }
    $dossiers[] = $entree;
}
sort($dossiers);

foreach ($dossiers as $dossier) {
    $ok = refusGlobal($racine . '/' . $dossier . '/.htaccess');
    printf("  %-28s %s\n", $dossier . '/', $ok ? 'fermé' : 'OUVERT');
    if (!$ok) {
        $ecarts[] = $dossier . '/ : servi tel quel si la racine web est celle du dépôt'
            . ' — il manque un .htaccess qui refuse tout le dossier';
    }
}

/* 2. les .htaccess promis par Deploiement::CODE.
   public/.htaccess y figure aussi, mais il ne barre rien : c'est la
   configuration du dossier SERVI — réécriture, en-têtes, cache. Exiger de lui
   un refus global reviendrait à demander au site de se fermer lui-même. Seule
   son existence est vérifiée ici. */
echo "---\n";
foreach (Deploiement::cheminsCode() as $chemin) {
    if (!str_ends_with($chemin, '.htaccess')) {
        continue;
    }
    $barriere = !in_array(explode('/', $chemin)[0], OUVERTS, true);
    $existe = is_file($racine . '/' . $chemin);
    $refuse = $existe && refusGlobal($racine . '/' . $chemin);
    printf(
        "  %-28s %s\n",
        $chemin,
        !$existe ? 'ABSENT' : ($barriere ? ($refuse ? 'ok' : 'SANS REFUS') : 'ok (dossier servi)')
    );
    if (!$existe) {
        $ecarts[] = $chemin . ' est déclaré dans Deploiement::CODE mais n’existe pas :'
            . ' une mise à jour ne le rétablirait pas sur un site déjà en ligne';
    } elseif ($barriere && !$refuse) {
        $ecarts[] = $chemin . ' existe mais ne refuse pas tout le dossier';
    }
}

// 3. les dossiers qui portent un secret
echo "---\n";
foreach (Permissions::secrets() as $secret) {
    $present = is_dir($racine . '/' . $secret);
    $ok = couvert($racine, $secret);
    printf(
        "  %-28s %s%s\n",
        $secret . '/',
        $ok ? 'couvert' : 'DÉCOUVERT',
        $present ? '' : '   (dossier absent pour l’instant)'
    );
    if (!$ok) {
        $ecarts[] = $secret . '/ contient un secret ou des données personnelles'
            . ' et aucun .htaccess ne le ferme, ni sur lui ni sur un parent';
    }
}

echo "---\n";
if ($ecarts === []) {
    printf(
        "%d dossier(s) fermé(s), %d secret(s) couvert(s). Rien d’atteignable hors public/.\n",
        count($dossiers),
        count(Permissions::secrets())
    );
    exit(0);
}

foreach ($ecarts as $e) {
    echo '  ECART  ' . $e . "\n";
}
printf("%d écart(s).\n", count($ecarts));
exit(1);
