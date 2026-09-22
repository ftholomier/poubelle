<?php
/**
 * Mise en page publique.
 *
 * @var string      $content    corps de page déjà rendu
 * @var string      $title      titre de l'onglet
 * @var string      $desc       méta description
 * @var string      $path       chemin sans préfixe de langue (pour hreflang)
 * @var bool        $translated page servie dans une langue traduite
 * @var string      $robots     directive robots explicite, sinon déduite
 * @var array       $schema     données structurées JSON-LD de la page
 */
use App\Core\Config;
use App\Core\Security;
use App\Services\I18n;
use App\Services\Seo;
use App\Services\StructuredData;

$lang = I18n::lang();
$siteName = (string) Config::get('site.name');
$base = rtrim((string) Config::get('site.url'), '/');
$path = $path ?? '/';

// Le nom du site clôt le titre, sauf s'il s'y trouve déjà : un gabarit de
// référencement peut l'avoir placé lui-même, par la variable {site} ou
// en toutes lettres. L'y répéter gâcherait deux fois la place que Google
// accorde à un titre.
$title = trim((string) ($title ?? ''));
$pageTitle = $title === '' ? $siteName
    : (str_contains($title, $siteName) ? $title : $title . ' · ' . $siteName);
$description = $desc ?? (string) Config::get('site.baseline');

/**
 * Une page servie en anglais mais rédigée en français n'est pas une version
 * anglaise : la déclarer comme telle a produit six copies indexables du même
 * contenu. Tant qu'une langue n'a pas de traduction, sa page renvoie vers
 * l'original et sort de l'index.
 */
$isUntranslated = !empty($untranslated);
$htmlLang = $isUntranslated ? 'fr' : $lang;
$canonical = $base . I18n::url($path, $isUntranslated ? 'fr' : $lang);

// La pagination mérite une adresse canonique propre à chaque page : renvoyer
// la page 3 vers la page 1 empêche l'indexation de ses résultats.
$page = (int) ($_GET['page'] ?? 1);
if ($page > 1) {
    $canonical .= '?page=' . $page;
}

$robots = ($robots ?? '') !== '' ? $robots : ($isUntranslated ? 'noindex, follow' : '');
$alternates = Seo::alternates($path, $isUntranslated);
$ogImage = Seo::ogImage($ogImage ?? '');
?>
<!DOCTYPE html>
<html lang="<?= e($htmlLang) ?>" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<?php if ($robots !== ''): ?>
<meta name="robots" content="<?= e($robots) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<?php foreach ($alternates as $code => $href): ?>
<link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e($base . $href) ?>">
<?php endforeach; ?>
<?php if ($alternates !== []): ?>
<link rel="alternate" hreflang="x-default" href="<?= e($base . I18n::url($path, 'fr')) ?>">
<?php endif; ?>

<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($title !== '' ? $title : $siteName) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:locale" content="<?= e(str_replace('-', '_', I18n::locale($htmlLang))) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($title !== '' ? $title : $siteName) ?>">
<meta name="twitter:description" content="<?= e($description) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">

<meta name="theme-color" content="#FFF7ED">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/img/icon-180.png">
<link rel="manifest" href="/site.webmanifest">
<?php // Les deux fontes du texte courant : préchargées, elles cessent de
      // dépendre de la découverte tardive de la feuille de style. ?>
<link rel="preload" as="font" type="font/woff2" crossorigin
      href="/assets/fonts/plusjakartasans-f7a01dbf.woff2">
<link rel="preload" as="font" type="font/woff2" crossorigin
      href="/assets/fonts/bricolagegrotesque-40c2cb34.woff2">
<link rel="stylesheet" href="<?= e(asset('/assets/fonts/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
<?= StructuredData::script($schema ?? [], Security::nonce()) ?>
</head>
<body<?= App\Services\Ads::scriptNeeded()
    ? ' data-ads-client="' . e(App\Services\Ads::client()) . '"'
      . ' data-ads-mode="' . e(App\Services\Ads::mode()) . '"'
      . ' data-ads-consent="' . e(App\Services\Ads::consentMode()) . '"'
    : '' ?>>
<a class="skip-link" href="#main"><?= e(I18n::t('nav.skip')) ?></a>

<?= App\Core\View::partial('partials/header', ['path' => $path]) ?>

<?php if (!empty($translated)): ?>
<div class="translated-banner" role="status">
  <?= App\Support\Icon::svg('translate', 15) ?>
  <span><?= e(I18n::t('banner.translated')) ?> · <span class="soft"><?= e(I18n::t('banner.translated_note')) ?></span></span>
  <a href="<?= e(I18n::url(I18n::stripPrefix($path), 'fr')) ?>">Français</a>
</div>
<?php elseif ($isUntranslated): ?>
<div class="translated-banner" role="status">
  <?= App\Support\Icon::svg('translate', 15) ?>
  <span><?= e(I18n::t('banner.untranslated', (string) (I18n::languages()[$lang]['name'] ?? $lang))) ?></span>
  <a href="<?= e(I18n::url(I18n::stripPrefix($path), 'fr')) ?>">Français</a>
</div>
<?php endif; ?>

<main id="main"><?= $content ?></main>

<?= App\Core\View::partial('partials/footer') ?>
<?= App\Core\View::partial('partials/cta-bar', ['path' => $path]) ?>
<?= App\Core\View::partial('partials/regie') ?>
<?= App\Core\View::partial('partials/exit-popup') ?>
<?php // Avec le CMP de Google, c'est lui qui demande : deux bandeaux nuiraient. ?>
<?php if (!App\Services\Ads::googleConsent()): ?>
  <?= App\Core\View::partial('partials/cmp') ?>
<?php endif; ?>
<?php if (($_GET['pub'] ?? '') === 'diag'): ?>
  <?= App\Core\View::partial('partials/ad-diag') ?>
<?php endif; ?>

<script src="<?= e(asset('/assets/js/app.js')) ?>" defer></script>
</body>
</html>
