<?php
/** @var string $content  @var array $meta  @var string $active */
$site = Content::site();
$ui = Content::ui();
$title = $meta['title'] ?? $site['name'];
$desc = $meta['description'] ?? '';
$canonical = $meta['canonical'] ?? null;
$image = $meta['image'] ?? '/media/video/renovest-new-poster.jpg';
$n = csp_nonce();
$jsCfg = [
    'devis' => Content::path('devis'),
    'contact' => Content::path('contact'),
    'cursor' => !empty($ui['cursor']),
    'exit' => ['enabled' => !empty($ui['exit']['enabled']), 'mode' => $ui['exit']['mode'] ?? 'session'],
    'teaser' => ['enabled' => !empty($ui['teaser']['enabled']) && !empty($ui['bot']['enabled']), 'delay' => (int) ($ui['teaser']['delay'] ?? 20)],
    'bot' => ['enabled' => !empty($ui['bot']['enabled']), 'welcome' => (string) ($ui['bot']['welcome'] ?? ''),
        'quick' => array_values(array_map(fn($q) => ['q' => (string) $q['q'], 'a' => (string) $q['a']], $ui['bot']['quick'] ?? [])),
        'ask' => (string) ($ui['bot']['ask'] ?? ''), 'askAnswer' => (string) ($ui['bot']['ask_answer'] ?? '')],
    'token' => Http::formToken(),
    'voice' => (function () {
        $v = Voice::cfg();
        return !empty($v['enabled']) ? ['max' => (int) $v['max_seconds'], 'questions' => array_values($v['questions']), 'title' => (string) $v['title'],
            'noTranscript' => (string) $v['no_transcript'], 'sentTitle' => (string) $v['sent_title'], 'errorTitle' => (string) $v['error_title']] : null;
    })(),
];
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<?php if ($desc !== ''): ?><meta name="description" content="<?= e($desc) ?>">
<?php endif; ?>
<?php if ($canonical): ?><link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>
<?php if (!empty($meta['noindex'])): ?><meta name="robots" content="noindex">
<?php endif; ?>
<meta property="og:locale" content="fr_FR">
<meta property="og:type" content="<?= e($meta['og_type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($title) ?>">
<?php if ($desc !== ''): ?><meta property="og:description" content="<?= e($desc) ?>">
<?php endif; ?>
<?php if ($canonical): ?><meta property="og:url" content="<?= e($canonical) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= e($site['name']) ?>">
<meta property="og:image" content="<?= e(Http::abs($image)) ?>">
<meta name="theme-color" content="#1c1c1c">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/favicons/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/favicons/apple-icon-180x180.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800;900&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('/assets/css/site.css')) ?>">
<script nonce="<?= e($n) ?>">document.documentElement.classList.add('js')</script>
<?php if (!empty($meta['jsonld'])): ?><script type="application/ld+json"><?= json_encode($meta['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<body class="<?= !empty($ui['cta_bar']['enabled']) ? 'has-cta' : '' ?>">
<a class="skip" href="#contenu">Aller au contenu</a>
<div class="rv-root">
<?php View::render('partials/header', ['active' => $active ?? '']) ?>
<main id="contenu">
<?= $content ?>
</main>
<?php View::render('partials/footer') ?>
</div>
<?php View::render('partials/floating') ?>
<script type="application/json" id="rv-config"><?= json_encode($jsCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('/assets/js/site.js')) ?>" defer></script>
</body>
</html>
