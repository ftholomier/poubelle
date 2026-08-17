<?php
/**
 * Gabarit de base — design noir & or.
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
// sous-menus reconstruits depuis les collections : un hébergement ajouté au
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
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($canonique) ?>">
<?php if (!$indexer): ?>
<meta name="robots" content="noindex, follow">
<?php endif; ?>
<meta property="og:site_name" content="<?= e($site['nom']) ?>">
<meta property="og:title" content="<?= e($titre) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="<?= $fiche !== null ? 'article' : 'website' ?>">
<meta property="og:url" content="<?= e($canonique) ?>">
<meta property="og:image" content="<?= e($partage) ?>">
<meta property="og:locale" content="fr_FR">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#0c0b09">
<link rel="icon" href="<?= asset('assets/img/logo/favicon-512.png') ?>" type="image/png">
<link rel="preload" href="<?= url('assets/fonts/playfair-display-latin-500-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= url('assets/fonts/cormorant-garamond-latin-400-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('assets/css/site.css') ?>">
<script type="application/ld+json"><?= $seo->jsonLd($cle, $fiche, base_absolue(), fn(string $c): string => absolu(image($c))) ?></script>
</head>
<body>
<a class="evitement" href="#contenu">Aller au contenu</a>
<?= $view->partial('header', ['site' => $site]) ?>
<main id="contenu"><?= $slot ?></main>
<?= $view->partial('footer', ['site' => $site]) ?>
<script src="<?= asset('assets/js/site.js') ?>" defer></script>
</body>
</html>
