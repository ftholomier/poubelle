<?php
/** @var array $g contenus communs  @var array $s paramètres  @var string $body  @var array $meta */
use LD\Http;

$meta = ($meta ?? []) + ['title' => '', 'description' => '', 'path' => Http::path(), 'active' => '', 'image' => null, 'noindex' => false, 'type' => 'website', 'jsonld' => [], 'bodyClass' => ''];
$suffix = (string) ($s['seo']['title_suffix'] ?? '');
$fullTitle = $meta['title'] !== '' ? $meta['title'] . (str_contains($meta['title'], 'LE-DIGITAL') ? '' : $suffix) : ($s['site']['name'] ?? 'LE-DIGITAL.com');
$canonical = abs_url($meta['path']);
$ogImage = abs_url($meta['image'] ?: ($s['site']['og_image'] ?? '/assets/img/fred-situation-1600.webp'));
$noindex = $meta['noindex'] || !empty($s['seo']['noindex']);
$cfg = [
    'token'      => LD\PageCache::TOKEN,
    'animations' => (bool) ($s['features']['animations'] ?? true),
    'consent'    => LD\Consent::enabled() ? LD\Consent::config() : null,
    'dock'       => [
        'greeting'      => $g['dock']['greeting'] ?? '',
        'error'         => $g['dock']['error_text'] ?? '',
        'briefDone'     => $g['dock']['brief_done'] ?? '',
        'briefTitle'    => $g['dock']['brief_title'] ?? '',
        'briefCta'      => $g['dock']['brief_cta'] ?? '',
        'statusOpen'    => $g['dock']['status_open'] ?? '',
        'statusClosed'  => $g['dock']['status_closed'] ?? '',
        'openFrom'      => (int) ($g['dock']['open_from'] ?? 9),
        'openTo'        => (int) ($g['dock']['open_to'] ?? 18),
        'questions'     => $g['dock']['voice_questions'] ?? [],
        'maxSeconds'    => (int) ($g['dock']['voice_max_seconds'] ?? 90),
        'voiceTitle'    => $g['dock']['voice_title'] ?? '',
        'sentTitle'     => $g['dock']['voice_sent_title'] ?? '',
        'errorTitle'    => $g['dock']['voice_error_title'] ?? '',
        'noTranscript'  => $g['dock']['voice_no_transcript'] ?? '',
        'socialProof'   => !empty($g['dock']['social_proof']) ? array_values($g['dock']['social_proof_messages'] ?? []) : [],
    ],
];
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<?php if ($meta['description'] !== ''): ?><meta name="description" content="<?= e($meta['description']) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if ($noindex): ?><meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<meta property="og:type" content="<?= e($meta['type']) ?>">
<meta property="og:site_name" content="<?= e($s['site']['name'] ?? 'LE-DIGITAL.com') ?>">
<meta property="og:locale" content="fr_FR">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<?php if ($meta['description'] !== ''): ?><meta property="og:description" content="<?= e($meta['description']) ?>">
<?php endif; ?>
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#cc1517">
<?php if (!empty($s['seo']['google_verification'])): ?><meta name="google-site-verification" content="<?= e($s['seo']['google_verification']) ?>">
<?php endif; ?>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="preload" href="/assets/fonts/montserrat-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/dmsans-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
<script>document.documentElement.classList.add('js')</script>
<?php foreach ((array) $meta['jsonld'] as $ld): ?>
<script type="application/ld+json"><?= json_script($ld) ?></script>
<?php endforeach; ?>
<?= LD\Consent::wrap('audience', (string) ($s['seo']['analytics_head'] ?? '')) ?>
</head>
<body class="<?= e($meta['bodyClass']) ?>">
<a class="skip" href="#contenu">Aller au contenu</a>
<div class="page">
<?= LD\View::partial('header', ['g' => $g, 's' => $s, 'active' => $meta['active']]) ?>
<main id="contenu">
<?= $body ?>
</main>
<?= LD\View::partial('footer', ['g' => $g, 's' => $s]) ?>
<?php if (!empty($g['dock']['enabled'])): ?>
<?= LD\View::partial('dock', ['g' => $g, 's' => $s]) ?>
<?php endif; ?>
<?php if (!empty($g['exit_popup']['enabled']) && !preg_match('#^/(on-prend-rdv|rdv/|mentions-legales)#', (string) $meta['path'])): ?>
<?= LD\View::partial('exit-popup', ['g' => $g, 's' => $s]) ?>
<?php endif; ?>
</div>
<?php if (LD\Consent::enabled()): ?>
<?= LD\View::partial('cookies', ['g' => $g]) ?>
<?php endif; ?>
<script id="ld-config" type="application/json"><?= json_script($cfg) ?></script>
<script src="<?= asset('js/site.js') ?>" defer></script>
</body>
</html>
