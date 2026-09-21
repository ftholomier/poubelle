<?php
/**
 * Mise en page du back-office. Pas de barre CTA, l'assistant reste disponible
 * mais collé en bas à droite (bottom: 22px).
 *
 * @var string $content  @var string $title  @var bool $withNav  @var array|null $user
 */
use App\Core\Config;
use App\Core\Csrf;
use App\Services\I18n;
use App\Support\Icon;

$entries = [
    ['/admin/tableau-de-bord', 'admin.dashboard',    '#FF4B3E'],
    ['/admin/contenus',        'admin.contents',     '#FFC531'],
    ['/admin/offres',          'admin.jobs',         '#6D4AFF'],
    ['/admin/cv',              'admin.cvs',          '#0FBFA4'],
    ['/admin/employeurs',      'admin.employers',    '#FF7AB8'],
    ['/admin/documents',       'admin.documents',    '#FF4B3E'],
    ['/admin/traductions',     'admin.translations', '#FFC531'],
    ['/admin/sauvegardes',     'admin.backups',      '#6D4AFF'],
    ['/admin/utilisateurs',    'admin.users',        '#0FBFA4'],
    ['/admin/offres-externes', 'admin.sources',      '#0FBFA4'],
    ['/admin/publicite',       'admin.ads',          '#FF7AB8'],
];
$current = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
?>
<!DOCTYPE html>
<html lang="fr" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e((string) Config::get('site.name')) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/app.css?v=<?= e(Config::get('storage.schema')) ?>">
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= e(Config::get('storage.schema')) ?>">
</head>
<body class="is-admin">

<?php if (!$withNav): ?>
  <div class="auth-wrap"><?= $content ?></div>
<?php else: ?>
<div class="admin-shell">
  <header class="admin-top">
    <div class="inner">
      <a class="logo logo-sm logo-light" href="/admin/tableau-de-bord">intermittent<span class="tld">.fr</span></a>
      <span class="spacer"></span>
      <?php if ($user !== null): ?>
        <span class="admin-who"><strong><?= e((string) $user['name']) ?></strong> · <?= e((string) $user['role']) ?></span>
      <?php endif; ?>
      <a class="btn btn-ghost-light btn-sm" href="<?= e(I18n::url('/')) ?>" target="_blank" rel="noopener">Voir le site</a>
      <form method="post" action="/admin/deconnexion" style="display:inline">
        <?= Csrf::field('logout') ?>
        <button type="submit" class="btn btn-ghost-light btn-sm"><?= e(I18n::t('nav.logout')) ?></button>
      </form>
    </div>
  </header>

  <div class="admin-body">
    <div class="admin-grid">
      <nav class="admin-nav" aria-label="<?= e(I18n::t('admin.dashboard')) ?>">
        <?php foreach ($entries as [$href, $key, $color]): ?>
          <a href="<?= e($href) ?>"<?= $current === $href ? ' aria-current="page"' : '' ?>>
            <span class="bullet" style="background:<?= e($color) ?>"></span><?= e(I18n::t($key)) ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <main><?= $content ?></main>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="/assets/js/app.js?v=<?= e(Config::get('storage.schema')) ?>" defer></script>
<script src="/assets/js/admin.js?v=<?= e(Config::get('storage.schema')) ?>" defer></script>
</body>
</html>
