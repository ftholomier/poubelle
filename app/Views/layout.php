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
<?php
// Canonique sans chaîne de requête : ?utm_source=… ne doit pas créer
// autant d'URL canoniques distinctes aux yeux des moteurs.
$canonPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$canonPath = $canonPath === '/' ? '/' : rtrim($canonPath, '/');
?>
<link rel="canonical" href="<?= e(rtrim((string) settings('site.url'), '/') . $canonPath) ?>">

<meta property="og:type" content="website">
<meta property="og:locale" content="fr_FR">
<meta property="og:site_name" content="<?= e(settings('site.name')) ?>">
<meta property="og:title" content="<?= e($meta['title'] ?? settings('site.meta_title')) ?>">
<meta property="og:description" content="<?= e($meta['description'] ?? settings('site.meta_description')) ?>">
<?php // Les réseaux sociaux n'affichent pas les SVG : la vignette est un PNG. ?>
<meta property="og:image" content="<?= e(rtrim((string) settings('site.url'), '/') . url('assets/img/og-cover.png')) ?>">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Devenez agent immobilier indépendant chez Suisse Immo">
<meta name="twitter:card" content="summary_large_image">

<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(url('assets/img/favicon-32.png')) ?>" type="image/png" sizes="32x32">
<?php // iOS ignore les icônes SVG : l'écran d'accueil a besoin d'un PNG 180×180. ?>
<link rel="apple-touch-icon" sizes="180x180" href="<?= e(url('assets/img/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(url('site.webmanifest')) ?>">

<link rel="preload" as="font" type="font/woff2" href="<?= e(asset('fonts/inter-var.woff2')) ?>" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="<?= e(asset('fonts/bricolage-grotesque-var.woff2')) ?>" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">

<?php
/**
 * Données structurées : un seul graphe par page, et chaque type n'est
 * émis que sur la page qu'il décrit réellement. Répéter la FAQ ou
 * l'offre d'emploi sur toutes les URL produit des doublons que les
 * moteurs pénalisent.
 */
$base = rtrim((string) settings('site.url'), '/');
$graph = [[
    '@type' => 'RealEstateAgent',
    '@id' => $base . '/#organisation',
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
]];

// L'offre est rattachée à la page qui permet réellement de postuler.
if ($canonPath === '/candidater') {
    // Recrutement permanent : la date d'ouverture glisse au premier jour
    // du mois courant et la validité court sur 90 jours, de sorte que
    // l'annonce n'apparaisse jamais expirée sans intervention manuelle.
    $graph[] = [
        '@type' => 'JobPosting',
        '@id' => $base . '/candidater#offre',
        'url' => $base . '/candidater',
        'title' => 'Agent commercial immobilier indépendant (H/F)',
        'description' => (string) content('hero.lead'),
        'employmentType' => 'CONTRACTOR',
        'datePosted' => date('Y-m-01'),
        'validThrough' => date('Y-m-d', strtotime('+90 days', (int) strtotime(date('Y-m-01')))),
        'hiringOrganization' => ['@id' => $base . '/#organisation'],
        'jobLocation' => array_map(static fn ($c) => [
            '@type' => 'Place',
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => $c, 'addressCountry' => 'FR'],
        ], (array) content('network.cities', [])),
        'directApply' => true,
    ];
}

// La FAQ n'existe que sur la page d'accueil.
if ($canonPath === '/' && content('faq.items', []) !== []) {
    $graph[] = [
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static fn ($f) => [
            '@type' => 'Question',
            'name' => $f['q'] ?? '',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a'] ?? ''],
        ], (array) content('faq.items', [])),
    ];
}

// Article : type BlogPosting, avec auteur, dates et éditeur.
if (isset($post) && is_array($post)) {
    $graph[] = [
        '@type' => 'BlogPosting',
        '@id' => $base . '/actualites/' . ($post['slug'] ?? '') . '#article',
        'mainEntityOfPage' => $base . '/actualites/' . ($post['slug'] ?? ''),
        'headline' => meta_trim((string) ($post['title'] ?? ''), 110),
        'description' => meta_trim((string) ($post['excerpt'] ?? ''), 158),
        'articleSection' => (string) ($post['category'] ?? ''),
        'datePublished' => (string) ($post['published_at'] ?? ''),
        'dateModified' => (string) ($post['updated_at'] ?? $post['published_at'] ?? ''),
        'author' => ['@type' => 'Organization', 'name' => (string) ($post['author'] ?? ($company['legal_name'] ?? 'Suisse Immo'))],
        'publisher' => ['@id' => $base . '/#organisation'],
        'inLanguage' => 'fr-FR',
    ];
}

// Fil d'Ariane : émis dès qu'il y a un niveau sous l'accueil.
$fil = [['Accueil', '/']];
$libelles = [
    '/le-reseau' => 'Le réseau',
    '/le-metier' => 'Le métier',
    '/candidater' => 'Candidater',
    '/actualites' => 'Actualités',
    '/contact' => 'Contact',
    '/mentions-legales' => 'Mentions légales',
    '/politique-de-confidentialite' => 'Politique de confidentialité',
];
if (isset($libelles[$canonPath])) {
    $fil[] = [$libelles[$canonPath], $canonPath];
} elseif (isset($post) && is_array($post)) {
    $fil[] = ['Actualités', '/actualites'];
    $fil[] = [(string) ($post['title'] ?? ''), '/actualites/' . ($post['slug'] ?? '')];
}
if (count($fil) > 1) {
    $graph[] = [
        '@type' => 'BreadcrumbList',
        'itemListElement' => array_map(static fn ($i, $n) => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $n[0],
            'item' => $base . $n[1],
        ], array_keys($fil), $fil),
    ];
}
?>
<script nonce="<?= e(csp_nonce()) ?>" type="application/ld+json"><?= json_encode(
    ['@context' => 'https://schema.org', '@graph' => $graph],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?></script>

<script nonce="<?= e(csp_nonce()) ?>">window.SI = { base: <?= json_encode(rtrim((string) settings('site.base_path', ''), '/')) ?>, csrf: <?= json_encode(Csrf::token()) ?> };</script>
</head>
<?php
// Animation des halos : activable et réglable depuis Back-office → Réglages.
$glowOn = (bool) settings('motion.glow', true);
$glowCycle = max(8, min(180, (int) settings('motion.glow_cycle', 34)));
?>
<body class="<?= e(trim($bodyClass . ($glowOn ? ' has-glow-motion' : ''))) ?>"<?= $glowOn ? ' style="--glow-cycle:' . $glowCycle . 's"' : '' ?>>
<?= icons_sprite() ?>
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
