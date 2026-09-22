<?php
/**
 * Mise en page publique.
 *
 * @var string      $content    corps de page déjà rendu
 * @var string      $title      titre de l'onglet
 * @var string      $desc       méta description
 * @var string      $path       chemin sans préfixe de langue (pour hreflang)
 * @var bool        $translated page servie dans une langue traduite
 */
use App\Core\Config;
use App\Core\Security;
use App\Services\I18n;

$lang = I18n::lang();
$siteName = (string) Config::get('site.name');
$pageTitle = ($title ?? '') !== '' ? $title . ' · ' . $siteName : $siteName;
$canonical = rtrim((string) Config::get('site.url'), '/') . I18n::url($path ?? '/');
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($desc ?? (string) Config::get('site.baseline')) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php foreach (I18n::alternates($path ?? '/') as $code => $href): ?>
<link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e(rtrim((string) Config::get('site.url'), '/') . $href) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= e(rtrim((string) Config::get('site.url'), '/') . I18n::url($path ?? '/', 'fr')) ?>">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($title ?? $siteName) ?>">
<meta property="og:description" content="<?= e($desc ?? (string) Config::get('site.baseline')) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta name="theme-color" content="#FFF7ED">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/app.css?v=<?= e(App\Core\Config::get('storage.schema')) ?>">
</head>
<body<?= App\Services\Ads::scriptNeeded()
    ? ' data-ads-client="' . e(App\Services\Ads::client()) . '"'
      . ' data-ads-mode="' . e(App\Services\Ads::mode()) . '"'
    : '' ?>>
<a class="skip-link" href="#main"><?= e(I18n::t('nav.skip')) ?></a>

<?= App\Core\View::partial('partials/header', ['path' => $path ?? '/']) ?>

<?php if (!empty($translated)): ?>
<div class="translated-banner" role="status">
  <?= App\Support\Icon::svg('translate', 15) ?>
  <span><?= e(I18n::t('banner.translated')) ?> · <span class="soft"><?= e(I18n::t('banner.translated_note')) ?></span></span>
  <a href="<?= e(I18n::url(I18n::stripPrefix($path ?? '/'), 'fr')) ?>">Français</a>
</div>
<?php elseif (!empty($untranslated)): ?>
<div class="translated-banner" role="status">
  <?= App\Support\Icon::svg('translate', 15) ?>
  <span><?= e(I18n::t('banner.untranslated', (string) (I18n::languages()[$lang]['name'] ?? $lang))) ?></span>
  <a href="<?= e(I18n::url(I18n::stripPrefix($path ?? '/'), 'fr')) ?>">Français</a>
</div>
<?php endif; ?>

<main id="main"><?= $content ?></main>

<?= App\Core\View::partial('partials/footer') ?>
<?= App\Core\View::partial('partials/cta-bar') ?>
<?= App\Core\View::partial('partials/regie') ?>
<?= App\Core\View::partial('partials/exit-popup') ?>
<?= App\Core\View::partial('partials/cmp') ?>

<script src="/assets/js/app.js?v=<?= e(App\Core\Config::get('storage.schema')) ?>" defer></script>
</body>
</html>
