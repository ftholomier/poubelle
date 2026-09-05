<?php
declare(strict_types=1);

/**
 * Contrôleur frontal — unique point d'entrée PHP exposé par le serveur web.
 * Tout le reste de l'application vit hors de ce répertoire.
 */

// Serveur de développement intégré : laisser passer les fichiers statiques.
if (PHP_SAPI === 'cli-server') {
    $fichier = __DIR__ . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
    if ($fichier !== __DIR__ . '/' && is_file($fichier)) {
        return false;
    }
}

/*
 * Deux implantations possibles sur un hébergement mutualisé :
 *  - recommandée : la racine web pointe sur public/, l'application est au-dessus
 *  - repli       : tout est dans public_html/, ce fichier est alors à la racine
 */
$amorce = dirname(__DIR__) . '/app/bootstrap.php';
if (!is_file($amorce)) {
    $amorce = __DIR__ . '/app/bootstrap.php';
}
if (!is_file($amorce)) {
    http_response_code(500);
    exit('Installation incomplète : le dossier app/ est introuvable.');
}

$config = require $amorce;
$GLOBALS['config'] = $config;

use App\Core\ConflitEcriture;
use App\Core\Content;
use App\Core\Entetes;
use App\Core\Frequentation;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

$content = new Content($config['paths']['data'], $config['paths']['modele']);
$view    = new View($config['paths']['views']);

$view->share('config', $config);
$view->share('content', $content);

$router = new Router();
require $config['paths']['app'] . '/routes.php';

/**
 * Cette requête compte-t-elle comme une page vue ?
 *
 * Le back-office, l'API, la tâche planifiée et les fichiers techniques n'ont
 * rien à faire dans la fréquentation du site : ce sont les passages de la
 * mairie et de ses outils, pas ceux des administrés. Les robots déclarés sont
 * écartés aussi — non par souci de pureté, mais parce qu'un site de commune
 * en reçoit plus que de visiteurs, et qu'un chiffre gonflé par eux ne sert à
 * personne.
 */
function pageVue(string $methode, string $adresse): bool
{
    if ($methode !== 'GET') {
        return false;
    }

    $chemin = (string) (parse_url($adresse, PHP_URL_PATH) ?: '/');
    foreach (['/admin', '/api/', '/taches/', '/assets/', '/sitemap', '/robots'] as $prefixe) {
        if (str_starts_with($chemin, $prefixe)) {
            return false;
        }
    }

    $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

    return $agent !== ''
        && preg_match('~bot|crawl|spider|slurp|curl|wget|python-requests|headless|monitor~i', $agent) !== 1;
}

$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$adresse = $_SERVER['REQUEST_URI'] ?? '/';

try {
    $sortie = $router->dispatch($methode, $adresse);

    /* La page vue est comptée APRÈS le rendu, et seulement s'il a réussi : une
       404 ou une erreur n'est pas une consultation. Voir App\Core\Frequentation
       pour ce qui est enregistré — une date, un chemin, un nombre, et rien
       d'autre. Le compteur ne lève jamais. */
    // http_response_code() rend le code déjà posé : le routeur met 404 avant
    // de rendre sa page d'erreur. Une adresse qui n'existe pas n'est pas une
    // page consultée, et la compter donnerait un palmarès de fautes de frappe.
    if (http_response_code() === 200 && pageVue($methode, $adresse)) {
        (new Frequentation($config['paths']['data'] . '/frequentation'))->noter($adresse);
    }

    /* Les en-têtes partent APRÈS le rendu et avant le premier octet : c'est ce
       qui permet à la politique de sécurité de n'autoriser que les cadres que
       la page a réellement montés. Le contrôleur assemble en mémoire, rien
       n'est écrit avant ce point. $parametresSite vient de routes.php, requis
       plus haut dans cette même portée. */
    Entetes::envoyer((string) (parse_url($adresse, PHP_URL_PATH) ?: '/'), $parametresSite);

    echo $sortie;
} catch (ConflitEcriture $e) {
    // Deux administrateurs sur le même écran : rien n'a été écrit, et ce
    // n'est pas une panne. On renvoie l'administrateur sur l'écran d'où il
    // venait, avec le message qui dit quoi faire.
    Session::flash('erreur', $e->getMessage());

    // Le renvoi ne suit que le chemin du référent, jamais son hôte : une
    // redirection ouverte se glisse vite dans un en-tête recopié tel quel.
    $retour = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
    $retour = is_string($retour) && str_starts_with($retour, '/admin') ? $retour : '/admin';

    header('Location: ' . $retour, true, 303);
} catch (Throwable $e) {
    error_log(sprintf('[%s] %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));

    if ($config['app']['debug']) {
        throw $e;
    }

    $sortie = $view->render('erreur', ['code' => 500, 'titre' => 'Une erreur est survenue']);
    http_response_code(500);
    Entetes::envoyer((string) (parse_url($adresse, PHP_URL_PATH) ?: '/'), $parametresSite);
    echo $sortie;
}
