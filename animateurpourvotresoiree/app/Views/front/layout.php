<?php
use App\Core\Auth;
use App\Core\Request;
use App\Core\Security;
use App\Core\Session;
use App\Core\Url;
use App\Services\Ads;
use App\Services\Seo;
use App\Services\Settings;

/** @var string $content @var array $meta */
$meta = ($meta ?? []) + ['title' => Settings::siteName(), 'description' => '', 'robots' => 'index, follow', 'canonical' => null, 'jsonld' => [], 'scripts' => [], 'body_class' => '', 'ads' => true, 'og_image' => null, 'type' => 'website'];
$canonical = $meta['canonical'] ?? Url::abs(Request::path());
$ogImage = $meta['og_image'] ?: (Settings::get('seo.og_image') ?: '/assets/img/og-default.png');
$pro = Session::active() ? Auth::pro() : null;
$aiChat = App\Services\Settings::aiOn('assistant');
$config = [
    'csrf' => Session::active() ? App\Core\Csrf::token() : null,
    'map' => App\Services\Geo::tiles(),
    'turnstile' => Settings::get('antispam.turnstile') ? (string) env('TURNSTILE_SITE_KEY', '') : '',
    'vapid' => Settings::get('features.push', true) ? App\Services\Push::publicKey() : '',
    'consent' => ['ga4' => (string) Settings::get('analytics.ga4', ''), 'pixel' => (string) Settings::get('analytics.meta_pixel', ''), 'ads' => Ads::enabled() && !Ads::demo() && !empty($meta['ads']) && Settings::get('ads.cmp', 'google') === 'own', 'adsClient' => Settings::get('ads.cmp', 'google') === 'own' ? Ads::client() : ''],
    'chat' => $aiChat ? ['name' => (string) Settings::get('ai.assistant_name', 'Confetti'), 'greeting' => (string) Settings::get('ai.assistant_greeting', '')] : null,
    'pwa' => (bool) Settings::get('features.pwa', true),
    'tick' => App\Core\App::needsTick(),
];
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(Seo::title((string) $meta['title'])) ?></title>
<?php if ($meta['description'] !== ''): ?><meta name="description" content="<?= e($meta['description']) ?>">
<?php endif; ?>
<meta name="robots" content="<?= e($meta['robots']) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if (!empty($meta['prev'])): ?><link rel="prev" href="<?= e(Url::abs($meta['prev'])) ?>"><?php endif; ?>
<?php if (!empty($meta['next'])): ?><link rel="next" href="<?= e(Url::abs($meta['next'])) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e(Settings::siteName()) ?>">
<meta property="og:locale" content="fr_FR">
<meta property="og:type" content="<?= e($meta['type']) ?>">
<meta property="og:title" content="<?= e($meta['title']) ?>">
<?php if ($meta['description'] !== ''): ?><meta property="og:description" content="<?= e($meta['description']) ?>"><?php endif; ?>
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e(Url::abs($ogImage)) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#fff6e8">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/assets/img/icon-192.png" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="preload" href="/assets/fonts/bricolage-grotesque-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/instrument-serif-italic-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<?php foreach ($meta['styles'] ?? [] as $css): ?><link rel="stylesheet" href="<?= e($css) ?>">
<?php endforeach; ?>
<?php if ($meta['jsonld']): ?><script type="application/ld+json"><?= Seo::graph(array_merge([Seo::organization(), Seo::website()], $meta['jsonld'])) ?></script>
<?php endif; ?>
<?php if (!empty($meta['ads'])): ?><?= Ads::headScript() ?><?php endif; ?>
<?= Security::withNonce((string) Settings::get('analytics.head_html', '')) ?>
<script type="application/json" id="apvs-config"><?= js($config) ?></script>
</head>
<body class="<?= e($meta['body_class']) ?>">
<a class="skip" href="#contenu">Aller au contenu</a>
<?= App\Core\View::partial('front/partials/header', ['pro' => $pro]) ?>
<?php $flashes = Session::active() ? Session::flashes() : []; if ($flashes): ?>
<div class="flashes" role="status">
<?php foreach ($flashes as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>
<main id="contenu">
<?= $content ?>
</main>
<?php if (!empty($meta['local_links'])): ?><?= App\Core\View::partial('front/partials/local-links') ?><?php endif; ?>
<?= App\Core\View::partial('front/partials/footer') ?>
<div class="toast-zone" aria-live="polite"></div>
<?php if ($aiChat): ?>
<button type="button" class="chat-launch" id="chat-launch" aria-haspopup="dialog"><span class="bubble">✨</span><span>Besoin d'aide ?</span></button>
<?php endif; ?>
<script src="<?= asset('js/app.js') ?>" defer<?= Security::attr() ?>></script>
<?php foreach ($meta['scripts'] as $s): ?><script src="<?= e($s) ?>" defer<?= Security::attr() ?>></script>
<?php endforeach; ?>
<?php if ($aiChat): ?><script src="<?= asset('js/chat.js') ?>" defer<?= Security::attr() ?>></script><?php endif; ?>
<?= Security::withNonce((string) Settings::get('analytics.body_html', '')) ?>
</body>
</html>
