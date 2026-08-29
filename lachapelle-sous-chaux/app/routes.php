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

$pages = new PageController($view, $content, $parametresSite, new Mailer($parametresSite), $seo, $antispam);
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
    // vie municipale
    '/le-conseil-municipal'         => '/conseil-municipal',
    '/les-commissions'              => '/commissions-et-comites',
    '/les-comites'                  => '/commissions-et-comites',
    '/test-commission'              => '/commissions-et-comites',
    '/comptes-rendus-municipaux'    => '/comptes-rendus-du-conseil',
    '/liens_service/comptes-rendus-municipaux' => '/comptes-rendus-du-conseil',
    '/budget'                       => '/budget-communal',
    '/plan-local-durbanisme-intercommunal-plui' => '/urbanisme',
    '/procedure-de-modification-simplifiee-du-plan-doccupation-des-sols' => '/urbanisme',

    // démarches
    '/liens_service/demarches'      => '/demarches',
    '/demarches/attestation-daccueil'                 => '/demarches/attestation-d-accueil',
    '/demarches/carte-didentite-passeport'            => '/demarches/carte-d-identite-passeport',
    '/demarches/declarations-prealables-de-travaux'   => '/demarches/declaration-prealable-de-travaux',
    '/demarches/elections-inscription'                => '/demarches/inscription-sur-les-listes-electorales',
    '/demarches/elections-procurations'               => '/demarches/procuration-de-vote',
    '/demarches/vote-electeurs-europeens'             => '/demarches/vote-des-electeurs-europeens',
    '/demarches/permis-damenager'                     => '/demarches/permis-d-amenager',
    '/demarches/recensement-militaire'                => '/demarches/recensement-citoyen',
    '/services_etat'                => '/services-de-l-etat',
    '/services_etat/agence-regionale-de-sante-a-r-s'  => '/services-de-l-etat',
    '/services_etat/d-d-c-s-p-p'                      => '/services-de-l-etat',
    '/services_etat/d-d-t'                            => '/services-de-l-etat',
    '/services_etat/delegation-militaire-departementale' => '/services-de-l-etat',

    // vie scolaire : quatre pages d'une ligne chacune, devenues les sections
    // d'une page qui se lit d'un trait
    '/informations'                 => '/vie-scolaire',
    '/livret'                       => '/vie-scolaire#livret-d-accueil',
    '/parascolaire'                 => '/vie-scolaire#periscolaire',
    '/restauration'                 => '/vie-scolaire#restauration',
    '/transports'                   => '/vie-scolaire#transport-scolaire',

    // découvrir
    '/histoire-de-la-commune'       => '/histoire-du-village',
    '/liens_service/histoire-de-la-commune' => '/histoire-du-village',
    '/les-associations-a-lachapelle-sous-chaux' => '/associations',
    '/evenements'                   => '/agenda',
    '/evenements/event-1'           => '/agenda',
    '/evenements/event-2'           => '/agenda',
    '/evenements/event-3'           => '/agenda',
    '/evenements/un-concert-magnifique-dedie-aux-seniors-du-village'
                                    => '/actualites/concert-pour-les-anciens-du-village',
    '/actu-1'                       => '/actualites/concert-pour-les-anciens-du-village',
    '/actu-2'                       => '/actualites',
    '/actu-3'                       => '/actualites',
    '/flash-information'            => '/flash-info',

    // vie pratique
    '/eau-assainissement'           => '/eau-et-assainissement',
    '/intercommunalite-communaute-de-communes-des-vosges-du-sud' => '/intercommunalite',
    '/centre-socio-culturel-de-la-haute-savoureuse' => '/vie-pratique#centre-socio-culturel',
    '/numeros-pratiques'            => '/numeros-utiles',
    '/https-lachapelle-sous-chaux-fr-vie-pratique-numeros-utiles' => '/numeros-utiles',

    // contact et pages de service
    '/liens_service/contacter-la-mairie' => '/contact',
    '/page-d-exemple'               => '/',

    // adresses courtes, plausibles au clavier ou dans un document imprimé
    '/accueil'                      => '/',
    '/mairie'                       => '/la-mairie',
    '/conseil'                      => '/conseil-municipal',
    '/commissions'                  => '/commissions-et-comites',
    '/comptes-rendus'               => '/comptes-rendus-du-conseil',
    '/urbanisme-plui'               => '/urbanisme',
    '/demarche'                     => '/demarches',
    '/etat-civil'                   => '/demarches',
    '/ecole'                        => '/vie-scolaire',
    '/histoire'                     => '/histoire-du-village',
    '/dechets'                      => '/gerer-mes-dechets',
    '/eau'                          => '/eau-et-assainissement',
    '/urgences'                     => '/numeros-utiles',
];
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
$router->get($c('budget'),             fn() => $pages->documents('budget', 'budgets'));
$router->get($c('urbanisme'),          fn() => $pages->simple('urbanisme'));

// Démarches
$router->get($c('demarches'),             fn() => $pages->demarches());
$router->get($c('demarches') . '/{slug}', fn(array $p) => $pages->demarche($p['slug']));
$router->get($c('demarches-en-ligne'),    fn() => $pages->simple('demarches-en-ligne'));
$router->get($c('services-etat'),         fn() => $pages->servicesEtat());
$router->get($c('ccas'),                  fn() => $pages->simple('ccas'));

// Vie scolaire
$router->get($c('vie-scolaire'),       fn() => $pages->simple('vie-scolaire'));

// Le village
$router->get($c('le-village'),         fn() => $pages->simple('le-village'));
$router->get($c('histoire'),           fn() => $pages->simple('histoire'));
$router->get($c('associations'),       fn() => $pages->associations());
$router->get($c('actualites'),             fn() => $pages->actualites());
$router->get($c('actualites') . '/{slug}', fn(array $p) => $pages->actualite($p['slug']));
$router->get($c('agenda'),             fn() => $pages->agenda());
$router->get($c('flash-info'),         fn() => $pages->documents('flash-info', 'flash-info'));

// Vie pratique
$router->get($c('vie-pratique'),       fn() => $pages->simple('vie-pratique'));
$router->get($c('dechets'),            fn() => $pages->simple('dechets'));
$router->get($c('eau'),                fn() => $pages->simple('eau'));
$router->get($c('intercommunalite'),   fn() => $pages->simple('intercommunalite'));
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
