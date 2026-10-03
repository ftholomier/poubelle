<?php
/**
 * Mise en page commune du site public.
 * Variables : $content (HTML), $page = [title, description, image, active, body_class, jsonld, noindex, path, scripts]
 */

use App\Core\Settings;
use App\Front\Site;
use App\Services\I18n;

$page = $page ?? [];
$path = $page['path'] ?? ($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($path, PHP_URL_PATH) ?: '/';
$meta = Site::meta($page, $path);
$lang = I18n::lang();
$chat = empty($page['no_chat']) && \App\Services\Rag::enabled();
?><!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($meta['title']) ?></title>
<meta name="description" content="<?= e($meta['description']) ?>">
<link rel="canonical" href="<?= e($meta['canonical']) ?>">
<?php foreach ($meta['alternates'] as $alt): ?>
<link rel="alternate" hreflang="<?= e($alt['lang']) ?>" href="<?= e($alt['href']) ?>">
<?php endforeach; ?>
<?php if ($meta['noindex']): ?><meta name="robots" content="noindex, follow"><?php endif; ?>
<?php if (!empty($page['prev'])): ?><link rel="prev" href="<?= e($page['prev']) ?>"><?php endif; ?>
<?php if (!empty($page['next'])): ?><link rel="next" href="<?= e($page['next']) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e($meta['site']) ?>">
<meta property="og:locale" content="<?= $lang === 'en' ? 'en_GB' : 'fr_FR' ?>">
<meta property="og:type" content="<?= e($meta['type']) ?>">
<meta property="og:title" content="<?= e($page['title'] ?? $meta['site']) ?>">
<meta property="og:description" content="<?= e($meta['description']) ?>">
<meta property="og:url" content="<?= e($meta['canonical']) ?>">
<meta property="og:image" content="<?= e($meta['og_image']) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#0E1F4D">
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<link rel="apple-touch-icon" href="/assets/img/logo-sochaux-retro.png">
<link rel="preload" href="/assets/fonts/big-shoulders-display-normal-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/newsreader-normal-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
<?php foreach ($page['styles'] ?? [] as $css): ?>
<link rel="stylesheet" href="<?= asset($css) ?>">
<?php endforeach; ?>
<?php if ($chat): ?><link rel="stylesheet" href="<?= asset('css/chat.css') ?>"><?php endif; ?>
<?php if ($meta['jsonld']): ?>
<script type="application/ld+json"><?= json_encode($meta['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
<script nonce="<?= csp_nonce() ?>">document.documentElement.classList.add('js');try{var z=parseFloat(localStorage.getItem('fcsm-textsize'));if(z>1)document.documentElement.style.zoom=z}catch(e){}</script>
</head>
<body class="<?= e($page['body_class'] ?? '') ?>">
<a class="skip-link" href="#contenu"><?= e(t('Aller au contenu')) ?></a>
<?php if (empty($page['bare'])): ?>
<?= \App\Core\View::partial('partials/header', ['active' => $page['active'] ?? '', 'path' => $path]) ?>
<?php endif; ?>
<main id="contenu">
<?php if (!empty($meta['untranslated'])): ?><p class="i18n-note"><span class="wrap">This page has not been translated into English yet: here is the original French version. <a href="<?= e(\App\Services\I18n::switchUrl($path, 'fr')) ?>" hreflang="fr" lang="fr">Version française</a></span></p><?php endif; ?>
<?= $content ?>
</main>
<?php if (empty($page['bare'])): ?>
<?= \App\Core\View::partial('partials/footer') ?>
<?php endif; ?>
<?= \App\Core\View::partial('partials/lightbox') ?>
<?= \App\Core\View::partial('partials/cookie') ?>
<?php if ($chat): ?>
<?= \App\Core\View::partial('partials/chat') ?>
<?php endif; ?>
<script src="<?= asset('js/site.js') ?>" defer></script>
<?php if ($chat): ?><script src="<?= asset('js/chat.js') ?>" defer></script><?php endif; ?>
<?php foreach ($page['scripts'] ?? [] as $js): ?>
<script src="<?= asset($js) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
