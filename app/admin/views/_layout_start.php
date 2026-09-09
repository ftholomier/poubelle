<?php
/** Ouverture du gabarit applicatif : barre latérale + en-tête d'écran. */

use App\Admin;
use App\Config;
use App\Csrf;
use App\Offices;
use App\Router;
use App\Store;
use App\Text;
use App\View;

$meta = Admin::MENU[$screen] ?? Admin::MENU['dash'];
echo View::admin('_head', ['title' => $meta['title']]);

$offices = $offices ?? Offices::all();
$catalogue = Store::read(Offices::FILE);
$lastUpdate = (string) ($catalogue['updatedAt'] ?? '');
?>
<div class="app">
  <aside class="side">
    <div class="side__brand">
      <span class="side__mark" aria-hidden="true"></span>
      <span>
        <span class="side__name">Le iOiO</span>
        <span class="side__role">BACK-OFFICE</span>
      </span>
    </div>

    <nav class="side__nav" aria-label="Sections du back-office">
      <?php foreach (Admin::MENU as $key => $item): ?>
        <a class="side__link<?= $screen === $key ? ' is-active' : '' ?>" href="<?= Text::e(Router::adminUrl($key)) ?>">
          <span class="side__dot"></span>
          <span><?= Text::e($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="side__foot">
      <a class="side__view" href="<?= Text::e(Config::basePath()) ?>/" target="_blank" rel="noopener">Voir le site →</a>
      <div class="side__user">
        <div class="side__email"><?= Text::e((string) $user['email']) ?></div>
        <div class="side__userRole"><?= Text::e(($user['role'] ?? 'editor') === 'admin' ? 'Administrateur' : 'Éditeur') ?></div>
        <form method="post" action="<?= Text::e(Router::adminUrl()) ?>">
          <?= Csrf::field('admin') ?>
          <input type="hidden" name="action" value="logout">
          <button class="side__logout" type="submit">Se déconnecter</button>
        </form>
      </div>
    </div>
  </aside>

  <div class="main">
    <header class="head">
      <div>
        <h1><?= Text::e($meta['title']) ?></h1>
        <div class="head__sub"><?= Text::e($meta['sub']) ?></div>
      </div>
      <div class="head__actions">
        <span class="pill-live">
          <span class="pill-live__dot"></span>
          <span>Catalogue à jour · <?= Text::e($lastUpdate !== '' ? Admin::humanDate($lastUpdate) : 'jamais publié') ?></span>
        </span>
        <a class="btn btn--ink btn--pill" href="<?= Text::e(Config::basePath()) ?>/nos-bureaux" target="_blank" rel="noopener">Voir la page bureaux</a>
      </div>
    </header>

    <?php if (!empty($flash)): ?>
      <div class="flash flash--<?= Text::e((string) ($flash['type'] ?? 'ok')) ?>" role="status"><?= Text::e((string) $flash['message']) ?></div>
    <?php endif; ?>
