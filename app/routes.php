<?php
declare(strict_types=1);

/**
 * Table de routage. Chargée par public/index.php avec $router, $view,
 * $content et $config déjà instanciés.
 *
 * Les adresses des pages viennent de Seo : changer un slug dans le
 * back-office change la route, sans toucher à ce fichier.
 *
 * @var App\Core\Router  $router
 * @var App\Core\View    $view
 * @var App\Core\Content $content
 * @var array            $config
 */

use App\Admin\ApparenceController;
use App\Controllers\ApiController;
use App\Controllers\PageController;
use App\Core\Antispam;
use App\Core\Assistant;
use App\Core\Avis;
use App\Core\Conversations;
use App\Core\Langues;
use App\Core\Mailer;
use App\Core\Parametres;
use App\Core\Seo;
use App\Core\Vivant;
use App\Core\Traducteur;

$parametresSite = new Parametres($config['paths']['data'] . '/admin/parametres.json');
$langues    = new Langues($config['paths']['data'] . '/langues.json');
$traducteur = new Traducteur($config['paths']['data'] . '/traductions');

// La langue se lit dans l'adresse : /en/nos-services est servi en anglais,
// /nos-services en français. Le préfixe est retiré avant le routage, si bien
// qu'une seule table de routes sert toutes les langues.
[$langue, $cheminSansLangue] = $langues->detecter(
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
);
if ($langue !== Langues::SOURCE) {
    $_SERVER['REQUEST_URI'] = $cheminSansLangue;
    $content->traduireEn($langue, $traducteur);
}

$seo = new Seo($config['paths']['data'] . '/seo.json', $content);
$seo->prefixerPar($langues->prefixe($langue));

$avis = new Avis($parametresSite, $config['paths']['cache'] . '/avis-google.json');

$assistant = new Assistant(
    $parametresSite,
    $content,
    $config['paths']['data'],
    $config['paths']['cache'] . '/assistant.json'
);

$antispam = new Antispam($parametresSite, $config['paths']['cache'] . '/antispam-quotas.json');

$vivant = new Vivant($content);
$pages = new PageController($view, $content, $parametresSite, new Mailer($parametresSite), $seo, $antispam, $vivant);
$conversations = new Conversations($config['paths']['data'] . '/assistant/conversations');

$api   = new ApiController($content, $assistant, $conversations, new Mailer($parametresSite), $parametresSite);

$view->share('seo', $seo);
$view->share('parametres', $parametresSite);
$view->share('langues', $langues);
$view->share('langue', $langue);
$view->share('avis', $avis);
$view->share('assistant', $assistant);
// Disposition du menu : réglée dans le back-office, lue par l'en-tête et le
// gabarit. Partagée ici pour qu'une page n'ait pas à la redemander.
$view->share('menuStyle', $parametresSite->get('apparence.menu', 'horizontal'));
// Taille du logo, et ce que la barre en fait : le gabarit les repose en jeton
// CSS et en classe sur <html>. Les deux passent par le contrôleur d'apparence
// plutôt que par un get() direct, pour que le bornage soit le même à
// l'écriture et à la lecture.
$view->share('logoHauteur', ApparenceController::hauteurLogo($parametresSite));
$view->share('logoDeborde', ApparenceController::logoDeborde($parametresSite));
// La couleur de la commune : le gabarit en repose la palette entière dans un
// bloc :root, en tête de document. La dérivation est faite une fois par rendu
// — une centaine de multiplications, invisibles à la mesure — plutôt qu'écrite
// dans site.css, qui est un fichier statique partagé par toutes les pages et
// mis en cache par le navigateur.
$view->share('charte', new App\Core\Charte(ApparenceController::couleur($parametresSite)));
// Actualités, agenda et Flash Info : le bandeau « En ce moment » les affiche
// sur toutes les pages, il ne peut donc pas les redemander à chaque vue.
$view->share('vivant', $vivant);
$GLOBALS['seo'] = $seo;
$GLOBALS['langue'] = $langue;
$GLOBALS['traducteur'] = $traducteur;

/** Chemin d'une page sans préfixe de langue : c'est ce que le routeur voit. */
$c = static fn(string $cle): string => $seo->cheminSource($cle);

// --- redirections permanentes -------------------------------------------
// Les adresses du site WordPress précédent. Elles sont référencées, imprimées
// dans le bulletin municipal et collées dans des courriels : les perdre ferait
// tomber sur une erreur des administrés qui cherchent une démarche.
$redirections = $seo->redirections() + [
    // --- l'ancien site Moduliti -------------------------------------------
    // Ses adresses sont des identifiants numériques suivis d'un long libellé
    // référencé : /page/4546_conseil-municipal-mairie-dangeot-90150-…php.
    // Elles sont indexées, imprimées dans le bulletin et collées dans des
    // courriels. Le routeur ne sait pas les reconnaître par leur libellé, qui
    // varie ; on redirige donc sur le seul identifiant, avec un joker.
    '/index.php'        => '/',
    '/actualite.php'    => '/actualites',
    '/infoalaune.php'   => '/info-a-la-une',
    '/albumphoto.php'   => '/album-photos',
    '/contact.php'      => '/contact',
    '/planacces.php'    => '/contact',
    '/reservation.php'  => '/salle-camille',
    '/ml.php'           => '/mentions-legales',

    // adresses courtes, plausibles au clavier ou dans un document imprimé
    '/accueil'          => '/',
    '/mairie'           => '/la-mairie',
    '/conseil'          => '/conseil-municipal',
    '/equipe-municipale'=> '/conseil-municipal',
    '/commissions'      => '/commissions-et-comites',
    '/comptes-rendus'   => '/comptes-rendus-du-conseil',
    '/conseils-municipaux' => '/comptes-rendus-du-conseil',
    '/deliberations'    => '/deliberations-et-arretes',
    '/arretes'          => '/deliberations-et-arretes',
    '/budget'           => '/budget-communal',
    '/demarche'         => '/demarches',
    '/etat-civil'       => '/demarches',
    '/demarches-administratives' => '/demarches',
    '/histoire'         => '/histoire-et-patrimoine',
    '/patrimoine'       => '/histoire-et-patrimoine',
    '/salle'            => '/salle-camille',
    '/salle-communale'  => '/salle-camille',
    '/quotidien'        => '/au-quotidien',
    '/village'          => '/le-village',
    '/bois'             => '/bois-et-forets',
    '/forets'           => '/bois-et-forets',
    '/affouage'         => '/demarches/affouage',
    '/ecole'            => '/vie-scolaire',
    '/dechets'          => '/gerer-mes-dechets',
    '/urgences'         => '/numeros-utiles',
    '/localisation'     => '/contact',
    '/album'            => '/album-photos',
];

// Les pages de l'ancien site : /page/<identifiant>_<libellé>.php. Le libellé
// a changé plusieurs fois pour un même identifiant — c'est donc lui seul qui
// fait foi.
$ancienneNumerotation = [
    '222'  => '/',
    '274'  => '/bois-et-forets',
    '278'  => '/associations',
    '279'  => '/associations',
    '280'  => '/associations',
    '281'  => '/salle-camille',
    '283'  => '/au-quotidien',
    '287'  => '/liens-utiles',
    '398'  => '/histoire-et-patrimoine',
    '3536' => '/demarches',
    '3786' => '/budget-communal',
    '4022' => '/associations',
    '4050' => '/publications',
    '4521' => '/conseil-municipal',
    '4546' => '/comptes-rendus-du-conseil',
    '4885' => '/commissions-et-comites',
    '4951' => '/deliberations-et-arretes',
    '4952' => '/deliberations-et-arretes',
];
$router->get('/page/{page}', function (array $p) use ($ancienneNumerotation): string {
    $identifiant = strtok((string) ($p['page'] ?? ''), '_');
    $cible = $ancienneNumerotation[$identifiant] ?? '/plan-du-site';

    http_response_code(301);
    header('Location: ' . lien($cible));
    return '';
});

foreach ($redirections as $ancienne => $nouvelle) {
    if ($ancienne === '' || $ancienne === $nouvelle) {
        continue;
    }
    $router->get($ancienne, function () use ($nouvelle): string {
        http_response_code(301);
        // lien() conserve le préfixe de langue : /en/histoire doit arriver
        // sur /en/histoire-du-village, pas sur le français
        header('Location: ' . lien($nouvelle));
        return '';
    });
}

// --- pages éditoriales ---------------------------------------------------
$router->get($c('accueil'),            fn() => $pages->accueil());

// La mairie
$router->get($c('la-mairie'),          fn() => $pages->simple('la-mairie'));
$router->get($c('conseil-municipal'),  fn() => $pages->conseilMunicipal());
$router->get($c('commissions'),        fn() => $pages->commissions());
$router->get($c('comptes-rendus'),     fn() => $pages->documents('comptes-rendus', 'comptes-rendus'));
// Les délibérations et arrêtés sont d'abord des PDF : la page les liste, et
// garde en dessous le texte qui explique ce qu'ils sont. Elle restait une page
// de texte seul, sans moyen d'y déposer un acte.
$router->get($c('deliberations'),      fn() => $pages->documents('deliberations', 'deliberations'));
$router->get($c('budget'),             fn() => $pages->documents('budget', 'budgets'));
$router->get($c('publications'),       fn() => $pages->documents('publications', 'publications'));
$router->get($c('urbanisme'),          fn() => $pages->simple('urbanisme'));

// Démarches
$router->get($c('demarches'),             fn() => $pages->demarches());
$router->get($c('demarches') . '/{slug}', fn(array $p) => $pages->demarche($p['slug']));
$router->get($c('demarches-en-ligne'),    fn() => $pages->simple('demarches-en-ligne'));
$router->get($c('services-etat'),         fn() => $pages->servicesEtat());
$router->get($c('ccas'),                  fn() => $pages->simple('ccas'));

// Le village
$router->get($c('le-village'),         fn() => $pages->simple('le-village'));
$router->get($c('histoire'),           fn() => $pages->simple('histoire'));
$router->get($c('salle-camille'),      fn() => $pages->simple('salle-camille'));
$router->get($c('bois-et-forets'),     fn() => $pages->simple('bois-et-forets'));
$router->get($c('associations'),       fn() => $pages->associations());
$router->get($c('album-photos'),       fn() => $pages->simple('album-photos'));

// La vie du village
$router->get($c('actualites'),             fn() => $pages->actualites());
$router->get($c('actualites') . '/{slug}', fn(array $p) => $pages->actualite($p['slug']));
$router->get($c('agenda'),             fn() => $pages->agenda());
$router->get($c('info-a-la-une'),      fn() => $pages->documents('info-a-la-une', 'flash-info'));

// Au quotidien
$router->get($c('au-quotidien'),       fn() => $pages->simple('au-quotidien'));
$router->get($c('dechets'),            fn() => $pages->simple('dechets'));
$router->get($c('vie-scolaire'),       fn() => $pages->simple('vie-scolaire'));
$router->get($c('intercommunalite'),   fn() => $pages->simple('intercommunalite'));
$router->get($c('liens-utiles'),       fn() => $pages->simple('liens-utiles'));
$router->get($c('numeros-utiles'),     fn() => $pages->numerosUtiles());

// Pages de service
$router->get($c('mentions-legales'),   fn() => $pages->simple('mentions-legales'));
$router->get($c('confidentialite'),    fn() => $pages->simple('confidentialite'));
$router->get($c('accessibilite'),      fn() => $pages->simple('accessibilite'));
$router->get($c('plan-du-site'),       fn() => $pages->planDuSite());

// --- écrire à la mairie et contact ---------------------------------------
// Deux formulaires, deux intentions : le contact cherche un numéro et des
// horaires, la demande en ligne engage un dossier — acte d'état civil,
// signalement de voirie, réservation de salle — et demande donc l'objet et
// l'adresse dans la commune. Poser ces questions à qui veut seulement joindre
// le secrétariat le ferait renoncer.
$router->get($c('demande'),   fn() => $pages->demande());
$router->post($c('demande'),  fn() => $pages->demandeEnvoi());
$router->get($c('contact'),   fn() => $pages->contact());
$router->post($c('contact'),  fn() => $pages->contactEnvoi());

// --- référencement -------------------------------------------------------
$router->get('/sitemap.xml', function () use ($seo, $langues): string {
    header('Content-Type: application/xml; charset=utf-8');
    return $seo->sitemap(base_absolue(), array_keys($langues->publiees()));
});
$router->get('/robots.txt', function () use ($seo): string {
    header('Content-Type: text/plain; charset=utf-8');
    return $seo->robots(base_absolue());
});

// --- API JSON ------------------------------------------------------------
$router->get('/api/demarches',        fn() => $api->collection('demarches'));
$router->get('/api/demarches/{slug}', fn(array $p) => $api->item('demarches', $p['slug']));
$router->get('/api/actualites',       fn() => $api->collection('actualites'));
$router->get('/api/agenda',           fn() => $api->collection('agenda'));
$router->post('/api/assistant',         fn() => $api->assistant());
$router->post('/api/assistant/contact', fn() => $api->assistantContact());

// --- back-office ---------------------------------------------------------
require __DIR__ . '/routes-admin.php';

$router->fallback(fn() => $pages->introuvable());
