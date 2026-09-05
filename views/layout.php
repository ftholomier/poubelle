<?php
/**
 * Gabarit de base — charte de la commune (bleu ardoise du blason, Montserrat).
 *
 * @var App\Core\View $view
 * @var array $config
 * @var App\Core\Content $content
 * @var App\Core\Seo $seo
 * @var string $slot
 * @var array|null $page
 * @var string|null $seoCle    page de référencement affichée
 * @var array|null $seoItem    fiche affichée, s'il y en a une
 */
$site  = $content->load('site');
// sous-menus reconstruits depuis les collections : une actualité publiée au
// back-office apparaît aussitôt dans la navigation
$site['menu'] = $content->menu($seo->basesCollections());

$cle   = $seoCle ?? 'accueil';
$fiche = $seoItem ?? null;

$meta  = $seo->meta($cle, $page ?? null, $fiche !== null);
$titre = $meta['titre'];
$desc  = $meta['description'];

$canonique = absolu(url($seo->chemin($cle, $fiche['slug'] ?? null)));
$partage   = absolu(image($fiche['image'] ?? $seo->imagePartage($cle)));

// noindex : réglage de la page, coupure globale du site, ou page de service
// (404, confirmation d'envoi) que rien ne justifie de faire remonter
$indexer = $seo->indexable($cle) && ($page['meta']['robots'] ?? '') !== 'noindex';
?>
<!doctype html>
<?php /* Les deux réglages du logo sont reposés ici plutôt que dans la feuille
         de style : site.css est un fichier statique, mis en cache par le
         navigateur et partagé par toutes les pages, alors que ces valeurs
         appartiennent au back-office et doivent s'appliquer au premier rendu,
         sans clignotement. La racine est le seul endroit qui satisfasse les
         deux, la hauteur de barre en descendant par héritage.
         La taille est un entier borné par ApparenceController, jamais une
         chaîne venue du formulaire ; le mode est un booléen.

         La disposition du menu est reposée ici en plus du <body>, où le script
         la lit déjà : les hauteurs de barre se déduisent de la taille du logo
         dans le bloc :root, et une media query ne peut pas voir une classe
         portée par le <body>. Sur <html>, tout se résout sur le même élément
         et le calcul reste d'un seul tenant. */ ?>
<?php $classesRacine = array_filter([
    $menuStyle === 'horizontal' ? 'menu-horizontal' : 'menu-lateral',
    ($logoDeborde ?? false) ? 'logo-deborde' : '',
]); ?>
<html lang="<?= e($langue) ?>" class="<?= e(implode(' ', $classesRacine)) ?>" style="--logo-ref: <?= (int) ($logoHauteur ?? 52) ?>px">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($canonique) ?>">
<?php $publiees = $langues->publiees(); ?>
<?php if (count($publiees) > 1): ?>
  <?php foreach ($publiees as $code => $l): ?>
    <link rel="alternate" hreflang="<?= e($code) ?>"
          href="<?= e(absolu(url($seo->cheminEn($code, $cle, $fiche['slug'] ?? null)))) ?>">
  <?php endforeach; ?>
  <link rel="alternate" hreflang="x-default"
        href="<?= e(absolu(url($seo->cheminEn(App\Core\Langues::SOURCE, $cle, $fiche['slug'] ?? null)))) ?>">
<?php endif; ?>
<?php if (!$indexer): ?>
<meta name="robots" content="noindex, follow">
<?php endif; ?>
<meta property="og:site_name" content="<?= e($site['nom']) ?>">
<meta property="og:title" content="<?= e($titre) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="<?= $fiche !== null ? 'article' : 'website' ?>">
<meta property="og:url" content="<?= e($canonique) ?>">
<meta property="og:image" content="<?= e($partage) ?>">
<meta property="og:locale" content="<?= e($langue === 'fr' ? 'fr_FR' : $langue) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php /* La couleur de la barre du navigateur suit la charte : elle était
         restée sur le vert de l'ancien site, et une commune qui change sa
         couleur dans le back-office se serait retrouvée avec une barre d'une
         autre teinte que ses pages. */ ?>
<meta name="theme-color" content="<?= e($charte->jetons()['bleu-fonce']) ?>">
<link rel="icon" href="<?= asset('assets/img/logo/favicon-512.png') ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= asset('assets/img/logo/apple-touch-icon.png') ?>">
<?php /* Une seule police, chargée en un seul fichier variable : la charte
         n'en prévoit pas d'autre, et le contraste vient des graisses. */ ?>
<link rel="preload" href="<?= url('assets/fonts/montserrat-latin-variable.woff2') ?>" as="font" type="font/woff2" crossorigin>
<?php /* La photo de bandeau, annoncée par la page (voir precharger_image).
         Posée en fond CSS, le navigateur ne la découvrirait qu'après avoir
         lu la feuille de style : deux allers-retours de retard sur la seule
         image que le visiteur regarde. */ ?>
<?php foreach (liens_de_precharge() as $__precharge): ?>
  <link rel="preload" href="<?= e($__precharge) ?>" as="image" fetchpriority="high">
<?php endforeach; ?>
<link rel="stylesheet" href="<?= asset('assets/css/site.css') ?>">
<?php /* La palette, dérivée de la couleur choisie au back-office.

         Elle est posée APRÈS la feuille, et l'ordre est le point important :
         les deux déclarent des jetons sur `:root`, à spécificité égale, et
         c'est donc la dernière lue qui gagne. Placé avant, ce bloc était
         proprement écrasé par site.css et le réglage restait sans effet.

         Elle n'est pas écrite DANS site.css non plus : la feuille est un
         fichier statique que le navigateur met en cache et que toutes les
         pages partagent, alors que ces valeurs appartiennent au réglage et
         doivent s'appliquer au premier rendu, sans clignotement. La feuille
         garde ainsi la charte livrée et reste lisible seule. */ ?>
<style><?= $charte->styleRacine() ?></style>
<?php /* Le nonce vient de App\Core\Entetes : il autorise ce script-ci, et
         lui seul. Tout <script> ajouté à un gabarit doit le porter, sans quoi
         le navigateur le refuse — silencieusement pour l'utilisateur, mais
         bruyamment dans la console, et mise-en-page.py le relève. */ ?>
<script nonce="<?= e(App\Core\Entetes::nonce()) ?>" type="application/ld+json"><?= $seo->jsonLd($cle, $fiche, base_absolue(), fn(string $c): string => absolu(image($c))) ?></script>
</head>
<body class="<?= $menuStyle === 'horizontal' ? 'menu-horizontal' : 'menu-lateral' ?>">
<a class="evitement" href="#contenu">Aller au contenu</a>
<?= $view->partial('header', ['site' => $site, 'cle' => $cle, 'fiche' => $fiche]) ?>
<?php /* Les avis ferment chaque page, juste au-dessus du pied. Ils sont placés
         ici plutôt que dans chaque vue : une page ajoutée plus tard les
         affichera sans qu'on ait à y penser, et le fragment se retire de
         lui-même quand les avis sont désactivés ou indisponibles.
         Le bloc reste dans <main> : hors de lui, il formerait une région
         orpheline, ni contenu principal ni pied de page. */ ?>
<main id="contenu"><?= $slot ?><?= $view->partial('avis') ?></main>
<?= $view->partial('footer', ['site' => $site, 'cle' => $cle, 'fiche' => $fiche]) ?>
<?= $view->partial('cookies', ['site' => $site, 'categories' => App\Core\Cookies::categories()]) ?>
<?= $view->partial('assistant') ?>
<?php $mesure = trim((string) $parametres->get('mesure.identifiant')); ?>
<?php if ($mesure !== ''): ?>
  <!-- Chargé uniquement si le visiteur accepte la mesure d'audience : tant
       qu'il n'a pas choisi, ce bloc reste du texte inerte. -->
  <script nonce="<?= e(App\Core\Entetes::nonce()) ?>" type="text/plain" data-cookies="mesure"
          src="https://www.googletagmanager.com/gtag/js?id=<?= e($mesure) ?>" async></script>
  <script nonce="<?= e(App\Core\Entetes::nonce()) ?>" type="text/plain" data-cookies="mesure">
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?= e($mesure) ?>', { anonymize_ip: true });
  </script>
<?php endif; ?>
<script nonce="<?= e(App\Core\Entetes::nonce()) ?>" src="<?= asset('assets/js/site.js') ?>" defer></script>
</body>
</html>
