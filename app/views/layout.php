<?php
/**
 * Gabarit du site public.
 * @var string $content
 * @var array  $seo      titre, description, image, noindex
 * @var array  $settings content/settings.json
 * @var string $route    nom de la route courante
 * @var array  $params   paramètres de route (pour les hreflang)
 */

use App\Config;
use App\Content;
use App\Csrf;
use App\I18n;
use App\Router;
use App\Text;
use App\View;

$lang = I18n::lang();
$basePath = Config::basePath();
$settings = $settings ?? Content::settings();
$seo = $seo ?? [];
$route = $route ?? 'home';
$params = $params ?? [];

$siteName = (string) ($settings['site']['name'] ?? 'Le iOiO');
$suffix = (string) ($settings['seo']['titleSuffix'] ?? $siteName);
$title = trim((string) ($seo['title'] ?? ''));
$fullTitle = $title === '' ? $suffix : ($title . ' — ' . $siteName);
$description = (string) ($seo['description'] ?? $settings['seo']['description'] ?? '');
$ogImage = (string) ($seo['ogImage'] ?? $settings['seo']['ogImage'] ?? '');
$canonical = Router::absolute($route, $lang, $params);
$alternates = Router::alternates($route, $params);
$analytics = $settings['analytics'] ?? [];
?>
<!doctype html>
<html lang="<?= Text::e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Text::e($fullTitle) ?></title>
<meta name="description" content="<?= Text::e($description) ?>">
<?php if (!empty($seo['noindex'])): ?>
<meta name="robots" content="noindex,follow">
<?php endif; ?>
<link rel="canonical" href="<?= Text::e($canonical) ?>">
<?php foreach ($alternates as $altLang => $altUrl): ?>
<link rel="alternate" hreflang="<?= Text::e($altLang) ?>" href="<?= Text::e($altUrl) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= Text::e($alternates[Config::DEFAULT_LANG] ?? $canonical) ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= Text::e($siteName) ?>">
<meta property="og:title" content="<?= Text::e($fullTitle) ?>">
<meta property="og:description" content="<?= Text::e($description) ?>">
<meta property="og:url" content="<?= Text::e($canonical) ?>">
<meta property="og:locale" content="<?= Text::e($lang === 'fr' ? 'fr_FR' : 'en_GB') ?>">
<?php foreach (Config::LANGS as $otherLang): if ($otherLang !== $lang): ?>
<meta property="og:locale:alternate" content="<?= Text::e($otherLang === 'fr' ? 'fr_FR' : 'en_GB') ?>">
<?php endif; endforeach; ?>
<?php if ($ogImage !== ''): ?>
<meta property="og:image" content="<?= Text::e(Config::baseUrl() . $ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>

<link rel="icon" href="<?= Text::e($basePath) ?>/assets/img/ioio-mark.png">
<link rel="apple-touch-icon" href="<?= Text::e($basePath) ?>/assets/img/ioio-mark.png">
<meta name="theme-color" content="#0E0E0E">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= Text::e($basePath) ?>/assets/css/site.css?v=<?= Text::e((string) @filemtime(Config::publicPath('assets/css/site.css'))) ?>">

<script type="application/ld+json"><?= json_encode(App\Seo::organization(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php if (!empty($jsonLd)): ?>
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
<?php if (($analytics['provider'] ?? 'none') === 'plausible' && !empty($analytics['domain'])): ?>
<script defer data-domain="<?= Text::e((string) $analytics['domain']) ?>" src="https://plausible.io/js/script.js"></script>
<?php elseif (($analytics['provider'] ?? 'none') === 'matomo' && !empty($analytics['src'])): ?>
<script>var _paq=window._paq=window._paq||[];_paq.push(['trackPageView'],['enableLinkTracking']);</script>
<script defer src="<?= Text::e((string) $analytics['src']) ?>"></script>
<?php endif; ?>
</head>
<body<?= empty($settings['sticky']['enabled']) ? ' class="no-sticky"' : '' ?>>

<a class="skip" href="#contenu"><?= Text::e(I18n::t('skip.content')) ?></a>

<?= View::partial('partials/topbar', ['settings' => $settings, 'route' => $route, 'params' => $params]) ?>
<?= View::partial('partials/header', ['settings' => $settings, 'route' => $route]) ?>

<main id="contenu"<?= $route === 'home' ? '' : ' class="page-enter"' ?>>
<?= $content ?>
</main>

<?= View::partial('partials/footer', ['settings' => $settings]) ?>
<?= View::partial('partials/sticky', ['settings' => $settings]) ?>
<?= View::partial('partials/bot', ['settings' => $settings]) ?>
<?= View::partial('partials/exit', ['settings' => $settings]) ?>

<script>
window.IOIO = {
  basePath: <?= json_encode($basePath) ?>,
  lang: <?= json_encode($lang) ?>,
  exitIntent: <?= !empty($settings['exit']['enabled']) ? 'true' : 'false' ?>,
  exitInactivity: <?= (int) ($settings['exit']['inactivitySeconds'] ?? 45) ?>,
  chatToken: <?= json_encode(Csrf::token('chat')) ?>,
  i18n: {
    botError: <?= json_encode(I18n::t('bot.error')) ?>,
    exitSent: <?= json_encode(I18n::t('exit.sent')) ?>
  }
};
</script>
<script defer src="<?= Text::e($basePath) ?>/assets/js/site.js?v=<?= Text::e((string) @filemtime(Config::publicPath('assets/js/site.js'))) ?>"></script>
</body>
</html>
