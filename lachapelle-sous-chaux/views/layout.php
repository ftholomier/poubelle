<?php
/**
 * Gabarit de base — charte de la commune (vert de sapin, vert du logo, Montserrat).
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
<html lang="<?= e($langue) ?>">
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
<meta name="theme-color" content="#1D3730">
<link rel="icon" href="<?= asset('assets/img/logo/favicon-512.png') ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= asset('assets/img/logo/apple-touch-icon.png') ?>">
<?php /* Une seule police, chargée en un seul fichier variable : la charte
         n'en prévoit pas d'autre, et le contraste vient des graisses. */ ?>
<link rel="preload" href="<?= url('assets/fonts/montserrat-latin-variable.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('assets/css/site.css') ?>">
<script type="application/ld+json"><?= $seo->jsonLd($cle, $fiche, base_absolue(), fn(string $c): string => absolu(image($c))) ?></script>
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
  <script type="text/plain" data-cookies="mesure"
          src="https://www.googletagmanager.com/gtag/js?id=<?= e($mesure) ?>" async></script>
  <script type="text/plain" data-cookies="mesure">
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?= e($mesure) ?>', { anonymize_ip: true });
  </script>
<?php endif; ?>
<script src="<?= asset('assets/js/site.js') ?>" defer></script>
</body>
</html>
