<?php
/**
 * Gabarit de base — design noir & or.
 *
 * @var App\Core\View $view
 * @var array $config
 * @var App\Core\Content $content
 * @var string $slot
 * @var array|null $page
 */
$site  = $content->load('site');
// sous-menus reconstruits depuis les collections : un hébergement ajouté au
// back-office apparaît aussitôt dans la navigation
$site['menu'] = $content->menu();
$titre = ($page['titre'] ?? null) ? $page['titre'] . ' - ' . $site['titre_seo'] : $site['titre_seo'];
$desc  = $page['meta']['description'] ?? $site['accroche'];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta property="og:title" content="<?= e($titre) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="fr_FR">
<meta name="theme-color" content="#0c0b09">
<link rel="icon" href="<?= asset('assets/img/logo/favicon-512.png') ?>" type="image/png">
<link rel="preload" href="<?= url('assets/fonts/playfair-display-latin-500-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= url('assets/fonts/cormorant-garamond-latin-400-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('assets/css/site.css') ?>">
</head>
<body>
<a class="evitement" href="#contenu">Aller au contenu</a>
<?= $view->partial('header', ['site' => $site]) ?>
<main id="contenu"><?= $slot ?></main>
<?= $view->partial('footer', ['site' => $site]) ?>
<script src="<?= asset('assets/js/site.js') ?>" defer></script>
</body>
</html>
