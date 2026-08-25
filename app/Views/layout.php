<?php
/** @var string $content_for_layout */
$meta = $meta ?? [];
$bodyClass = $bodyClass ?? '';
$page = $page ?? '';
$company = (array) settings('company');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($meta['title'] ?? settings('site.meta_title')) ?></title>
<meta name="description" content="<?= e($meta['description'] ?? settings('site.meta_description')) ?>">
<?php if (!empty($meta['noindex'])): ?><meta name="robots" content="noindex, follow"><?php endif; ?>
<meta name="theme-color" content="#07080c">
<link rel="canonical" href="<?= e(rtrim((string) settings('site.url'), '/') . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">

<meta property="og:type" content="website">
<meta property="og:locale" content="fr_FR">
<meta property="og:site_name" content="<?= e(settings('site.name')) ?>">
<meta property="og:title" content="<?= e($meta['title'] ?? settings('site.meta_title')) ?>">
<meta property="og:description" content="<?= e($meta['description'] ?? settings('site.meta_description')) ?>">
<meta property="og:image" content="<?= e(rtrim((string) settings('site.url'), '/') . url('assets/img/og-cover.svg')) ?>">
<meta name="twitter:card" content="summary_large_image">

<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(url('assets/img/favicon.svg')) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">

<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'RealEstateAgent',
            '@id' => rtrim((string) settings('site.url'), '/') . '/#organisation',
            'name' => $company['legal_name'] ?? 'Suisse Immo',
            'url' => $company['main_site'] ?? '',
            'telephone' => $company['phone'] ?? '',
            'email' => $company['email'] ?? '',
            'vatID' => $company['vat'] ?? '',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $company['address'] ?? '',
                'postalCode' => $company['zip'] ?? '',
                'addressLocality' => $company['city'] ?? '',
                'addressCountry' => 'FR',
            ],
            'areaServed' => content('network.cities', []),
        ],
        [
            '@type' => 'JobPosting',
            'title' => 'Agent commercial immobilier indépendant (H/F)',
            'description' => (string) content('hero.lead'),
            'employmentType' => 'CONTRACTOR',
            'hiringOrganization' => ['@type' => 'Organization', 'name' => $company['legal_name'] ?? 'Suisse Immo'],
            'jobLocation' => array_map(static fn ($c) => [
                '@type' => 'Place',
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => $c, 'addressCountry' => 'FR'],
            ], (array) content('network.cities', [])),
            'directApply' => true,
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn ($f) => [
                '@type' => 'Question',
                'name' => $f['q'] ?? '',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a'] ?? ''],
            ], (array) content('faq.items', [])),
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<script>window.SI = { base: <?= json_encode(rtrim((string) settings('site.base_path', ''), '/')) ?>, csrf: <?= json_encode(Csrf::token()) ?> };</script>
</head>
<?php
// Animation des halos : activable et réglable depuis Back-office → Réglages.
$glowOn = (bool) settings('motion.glow', true);
$glowCycle = max(8, min(180, (int) settings('motion.glow_cycle', 34)));
?>
<body class="<?= e(trim($bodyClass . ($glowOn ? ' has-glow-motion' : ''))) ?>"<?= $glowOn ? ' style="--glow-cycle:' . $glowCycle . 's"' : '' ?>>
<a class="skip-link" href="#main">Aller au contenu</a>
<div class="progress" aria-hidden="true"></div>

<?php partial('header', ['page' => $page]); ?>

<main id="main"><?= $content_for_layout ?></main>

<?php partial('footer'); ?>
<?php if (settings('funnel.sticky_cta', true) && $page !== 'apply'): ?><?php partial('sticky-cta', ['page' => $page]); ?><?php endif; ?>
<?php if (settings('funnel.exit_intent', true) && $page !== 'apply'): ?><?php partial('exit-modal'); ?><?php endif; ?>
<?php if (Bot::isReady()): ?><?php partial('bot-widget'); ?><?php endif; ?>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if (($page ?? '') === 'apply'): ?><script src="<?= e(asset('js/funnel.js')) ?>" defer></script><?php endif; ?>
<?php if (Bot::isReady()): ?><script src="<?= e(asset('js/bot.js')) ?>" defer></script><?php endif; ?>
</body>
</html>
