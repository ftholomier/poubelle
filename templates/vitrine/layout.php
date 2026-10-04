<?php
/**
 * Mise en page du site de l'association (www). Même charte que le musée, plus aérée.
 * Variables : $content, $page = [title, full_title, description, image, active, body_class, jsonld, noindex, path, styles, scripts]
 */

use App\Vitrine\Host;
use App\Vitrine\Site;

$page = $page ?? [];
$path = $page['path'] ?? Site::path();
$meta = Site::meta($page, $path);
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($meta['title']) ?></title>
<meta name="description" content="<?= e(meta_description((string) $meta['description'])) ?>">
<link rel="canonical" href="<?= e($meta['canonical']) ?>">
<?php if ($meta['noindex']): ?><meta name="robots" content="noindex, follow"><?php endif; ?>
<meta property="og:site_name" content="<?= e($meta['site']) ?>">
<meta property="og:locale" content="fr_FR">
<meta property="og:type" content="<?= e($meta['type']) ?>">
<meta property="og:title" content="<?= e(($page['title'] ?? '') ?: $meta['site']) ?>">
<meta property="og:description" content="<?= e($meta['description']) ?>">
<meta property="og:url" content="<?= e($meta['canonical']) ?>">
<meta property="og:image" content="<?= e($meta['og_image']) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#0E1F4D">
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<link rel="apple-touch-icon" href="/assets/img/logo-sochaux-retro.png">
<link rel="preload" href="/assets/fonts/big-shoulders-display-normal-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/newsreader-normal-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
<link rel="stylesheet" href="<?= asset('css/vitrine.css') ?>">
<?php foreach ($page['styles'] ?? [] as $css): ?>
<link rel="stylesheet" href="<?= asset($css) ?>">
<?php endforeach; ?>
<?php if ($meta['jsonld']): ?>
<script type="application/ld+json"><?= json_encode($meta['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
<script nonce="<?= csp_nonce() ?>">document.documentElement.classList.add('js');</script>
</head>
<body class="vt <?= e($page['body_class'] ?? '') ?>">
<a class="skip-link" href="#contenu">Aller au contenu</a>
<?php if (Site::$preview): ?>
<div class="team-bar vt-preview" role="note"><span class="wrap"><b>Aperçu du site de l’association</b> — <?= Site::open() ? 'ouvert au public sur <a href="' . e(Host::abs($path)) . '" target="_blank" rel="noopener">' . e(Host::host()) . '</a>' : 'fermé au public (page d’attente)' ?> : les contenus « à vérifier » n’apparaissent qu’ici. <a href="/admin/association">Retour au back-office</a></span></div>
<?php endif; ?>
<?= \App\Core\View::partial('vitrine/partials/header', ['active' => $page['active'] ?? '']) ?>
<main id="contenu">
<?= $content ?>
</main>
<?= \App\Core\View::partial('vitrine/partials/footer') ?>
<?= \App\Core\View::partial('partials/lightbox') ?>
<script src="<?= asset('js/site.js') ?>" defer></script>
<script src="<?= asset('js/vitrine.js') ?>" defer></script>
<?php foreach ($page['scripts'] ?? [] as $js): ?>
<script src="<?= asset($js) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
