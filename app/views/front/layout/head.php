<?php
/**
 * <head> du site public : métadonnées, hreflang, données structurées,
 * jetons de thème injectés depuis le back-office.
 *
 * @var array $settings
 * @var string $lang
 * @var array $languages
 * @var array|null $page
 */

use App\Content\Settings;
use App\I18n\Translator;

$colors  = $settings['brand']['colors'] ?? [];

/** Triplet RVB d'une couleur hexadécimale, pour les halos et voiles translucides. */
$rgb = static function (string $hex, string $fallback = '0,137,247'): string {
    $hex = ltrim(trim($hex), '#');
    if (!preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
        return $fallback;
    }
    return implode(', ', [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))]);
};
$fonts   = $settings['brand']['fonts'] ?? [];
$site    = $settings['site'] ?? [];
$default = Settings::str('i18n.default', 'fr');

$pageTitle = '';
$pageDesc  = '';
$ogImage   = (string) ($settings['seo']['og_image'] ?? '');
$noindex   = false;
$slug      = '';

if (!empty($page)) {
    $slug      = (string) $page['slug'];
    $pageTitle = trRaw($page['seo']['title'] ?? '') ?: trRaw($page['title'] ?? '');
    $pageDesc  = trRaw($page['seo']['description'] ?? '') ?: trRaw($settings['seo']['description'] ?? '');
    $ogImage   = (string) ($page['seo']['og_image'] ?? '') ?: $ogImage;
    $noindex   = !empty($page['seo']['noindex']);
}
$pageTitle = $pageTitle !== '' ? $pageTitle : (string) ($site['name'] ?? '');
$fullTitle = $pageTitle . (string) ($settings['seo']['title_suffix'] ?? '');

$base     = (new App\Http\Request())->baseUrl();
$homeSlug = App\Content\Pages::homeSlug();
$path     = ($slug === '' || $slug === $homeSlug) ? '/' : '/' . $slug;
$canonical = $base . ($lang === $default ? '' : '/' . $lang) . ($path === '/' ? '/' : $path);
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e(mb_substr($pageDesc, 0, 300)) ?>">
<meta name="robots" content="<?= $noindex ? 'noindex,nofollow' : e((string) ($settings['seo']['robots'] ?? 'index,follow')) ?>">
<meta name="theme-color" content="<?= e($colors['ink'] ?? '#161922') ?>">
<link rel="canonical" href="<?= e($canonical) ?>">

<?php /* Déclaration des langues : hreflang pour chaque version publiée */ ?>
<?php foreach ($languages as $code): ?>
    <link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e($base . ($code === $default ? '' : '/' . $code) . ($path === '/' ? '/' : $path)) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= e($base . ($path === '/' ? '/' : $path)) ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e((string) ($site['name'] ?? '')) ?>">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e(mb_substr($pageDesc, 0, 300)) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:locale" content="<?= e(Translator::locale($lang)) ?>">
<?php if ($ogImage !== ''): ?>
    <meta property="og:image" content="<?= e(str_starts_with($ogImage, 'http') ? $ogImage : $base . $ogImage) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">

<link rel="icon" href="<?= e((string) ($site['favicon'] ?? '/assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e((string) ($site['favicon'] ?? '/assets/img/favicon.svg')) ?>">

<?php if (!empty($fonts['google'])): ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?= e((string) $fonts['google']) ?>" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?= e((string) $fonts['google']) ?>"></noscript>
<?php endif; ?>

<link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">

<?php /* Charte graphique pilotée depuis le back-office */ ?>
<style id="brand-tokens">
    :root {
        --c-ink: <?= e($colors['ink'] ?? '#161922') ?>;
        --c-ink-soft: <?= e($colors['ink_soft'] ?? '#242326') ?>;
        --c-primary: <?= e($colors['primary'] ?? '#921732') ?>;
        --c-primary-dark: <?= e($colors['primary_dark'] ?? '#7A1129') ?>;
        --c-accent: <?= e($colors['accent'] ?? '#0089F7') ?>;
        --c-accent-rgb: <?= $rgb((string) ($colors['accent'] ?? ''), '0,137,247') ?>;
        --c-primary-rgb: <?= $rgb((string) ($colors['primary'] ?? ''), '146,23,50') ?>;
        --c-accent-dark: <?= e($colors['accent_dark'] ?? '#006EDF') ?>;
        --c-mint: <?= e($colors['mint'] ?? '#32C5FD') ?>;
        --c-bg: <?= e($colors['bg'] ?? '#FFFFFF') ?>;
        --c-surface: <?= e($colors['surface'] ?? '#F7F7F7') ?>;
        --c-border: <?= e($colors['border'] ?? '#E4E4E6') ?>;
        --c-muted: <?= e($colors['muted'] ?? '#626262') ?>;
        --font-heading: <?= $fonts['heading'] ?? "'Space Grotesk', system-ui, sans-serif" ?>;
        --font-body: <?= $fonts['body'] ?? "'Poppins', system-ui, sans-serif" ?>;
        --radius: <?= e($settings['brand']['radius'] ?? '14px') ?>;
    }
</style>

<?php /* Données structurées : entreprise locale + avis */ ?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'AccountingService',
    'name'     => (string) ($site['name'] ?? ''),
    'description' => mb_substr(trRaw($settings['seo']['description'] ?? ''), 0, 300),
    'url'      => $base,
    'email'    => (string) ($site['email'] ?? ''),
    'telephone'=> (string) ($site['phone'] ?? ''),
    'image'    => $base . (string) ($site['logo'] ?? ''),
    'address'  => [
        '@type'           => 'PostalAddress',
        'streetAddress'   => (string) ($site['address'] ?? ''),
        'postalCode'      => (string) ($site['zip'] ?? ''),
        'addressLocality' => (string) ($site['city'] ?? ''),
        'addressCountry'  => 'FR',
    ],
    'founder' => ['@type' => 'Person', 'name' => (string) ($settings['legal']['director'] ?? '')],
    'sameAs'  => array_values(array_filter(array_map('strval', $settings['social'] ?? []))),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>
</script>
