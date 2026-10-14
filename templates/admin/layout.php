<?php
/**
 * Coque du back-office. Variables : $content, $meta (title, crumb, nav, tabs, actions), $user, $badges, $side
 */
use App\Admin\Base;
use App\Core\Auth;

$nav = $meta['nav'] ?? '';
$isAdmin = Auth::isAdmin();
$new = Base::createLinks($isAdmin);
$favs = \App\Admin\Favorites::mine();
$here = \App\Admin\Favorites::here($meta);
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf" content="<?= e(\App\Core\Session::csrfToken()) ?>">
<title><?= e(($meta['title'] ?? 'Back-office') . ' · Back-office ' . \App\Core\Club::s('site')) ?></title>
<link rel="icon" href="<?= e(\App\Core\Club::s('favicon')) ?>" type="image/png">
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('admin/admin.css') ?>">
<?= \App\Core\Club::themeCss() ?>
</head>
<body data-idle="<?= Auth::idleLimit() ?>">
<?php $compact = ($_COOKIE['bo_nav'] ?? '') === 'compact'; ?>
<div class="bo<?= $compact ? ' nav-compact' : '' ?>" data-bo>
  <aside class="side" id="side">
    <a class="side__brand" href="/admin"><img src="<?= e(\App\Core\Club::s('logo_png')) ?>" alt=""><span><b><?= e(\App\Core\Club::s('site_lc')) ?></b><small>Back-office</small></span></a>
    <label class="side__mode" title="Menu compact : seules les grandes parties, leurs rubriques s’ouvrent au survol">
      <span>Menu compact</span>
      <input type="checkbox" role="switch" data-nav-mode<?= $compact ? ' checked' : '' ?>><i aria-hidden="true"></i>
    </label>
    <nav aria-label="Menu du back-office">
      <?php foreach (Base::nav() as $group => $items): ?>
        <?php if (!$isAdmin && !array_filter($items, fn ($it) => !$it[3])) { continue; } ?>
        <?php
          $vis = array_filter($items, fn ($it) => $isAdmin || !$it[3]);
          $on = in_array($nav, array_column($vis, 0), true);
          $sum = array_sum(array_map(fn ($it) => (int) ($badges[$it[0]] ?? 0), $vis));
          $pink = !empty($badges['qualite']) && in_array('qualite', array_column($vis, 0), true);
        ?>
        <div class="side__part<?= $on ? ' is-on' : '' ?>">
          <span class="side__group"><span><?= e($group) ?></span><?php if ($sum): ?><span class="side__badge side__badge--sum<?= $pink ? ' side__badge--pink' : '' ?>"><?= $sum ?></span><?php endif; ?><i aria-hidden="true">›</i></span>
          <div class="side__items">
            <span class="side__flytitle" aria-hidden="true"><?= e($group) ?></span>
          <?php foreach ($vis as [$key, $label, $href, $adminOnly]): ?>
            <a class="side__item<?= $nav === $key ? ' is-on' : '' ?>" href="<?= e($href) ?>"<?= $nav === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if (!empty($badges[$key])): ?><span class="side__badge<?= $key === 'qualite' ? ' side__badge--pink' : '' ?>"><?= (int) $badges[$key] ?></span><?php endif; ?></a>
          <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </nav>
    <div class="side__foot">
      <?php $b = $side['backup']; ?>
      <?php if ($isAdmin): ?><span><i class="dot<?= $side['backup_ok'] ? '' : ($b ? ' dot--warn' : ' dot--ko') ?>"></i><?= $b ? 'Sauvegarde du ' . e(Base::ago($b['at'])) : 'Aucune sauvegarde' ?></span><?php endif; ?>
      <?php if (\App\Front\Site::centenaryCountdown()): ?><span style="color:var(--mist)">J-<?= (int) $side['days'] ?> avant le centenaire</span><?php endif; ?>
    </div>
  </aside>

  <div class="main">
    <header class="top">
      <button type="button" class="btn btn--sm side__toggle" data-nav-toggle aria-controls="side" aria-expanded="false">☰</button>
      <div class="top__title">
        <span class="top__crumb"><?= $meta['crumb_html'] ?? e($meta['crumb'] ?? '') ?></span>
        <h1 class="top__h"><span class="top__ht"><?= e($meta['title'] ?? '') ?></span></h1>
      </div>
      <button type="button" class="top__search" data-qk-open aria-label="Rechercher partout (Ctrl+K)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?= \App\Core\Club::color('navy') ?>" stroke-width="2.6" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/></svg>
        <span>Rechercher partout (fiches, médias, personnes)…</span><kbd>Ctrl K</kbd>
      </button>
      <div class="me" data-dropdown>
        <button type="button" class="btn btn--yellow" data-dropdown-toggle aria-expanded="false">+ Nouveau</button>
        <div class="menu" hidden>
          <?php foreach ($new as [$l, $h]): ?><a href="<?= e($h) ?>"><?= e($l) ?></a><?php endforeach; ?>
        </div>
      </div>
      <?php if (\App\Core\Auth::isAdmin()): // Compteur mémoire (administrateurs) : OPcache et Redis, en Mo.
        $mo = \App\Services\ServerCheck::opcache(); $mr = null;
        if ($rcx = \App\Core\PhpCache::redis()) { try { $ri = $rcx->info('memory'); $mr = [round((int) $ri['used_memory'] / 1048576, 1), (int) ($ri['maxmemory'] ?? 0) / 1048576]; } catch (\Throwable) {} }
        $lvl = fn (float $r) => $r >= 0.9 ? 'ko' : ($r >= 0.7 ? 'warn' : 'ok');
        if ($mo || $mr): ?>
      <a class="top__mem" href="/admin/taches#serveur" title="Mémoire du serveur : OPcache (Mo utilisés / total, part trouvée en mémoire) et Redis (Mo), détail dans Serveur / tâches planifiées)">
        <?php if ($mo): ?><span class="<?= $lvl($mo['total'] ? $mo['used'] / $mo['total'] : 0) ?>">OPc <b><?= $mo['used'] ?></b><small>/<?= $mo['total'] ?> (<?= e((string) round($mo['hits'])) ?> %)</small></span><?php endif; ?>
        <?php if ($mr): ?><span class="<?= $lvl($mr[1] ? $mr[0] / $mr[1] : 0) ?>">Red <b><?= e(number_format($mr[0], 1, ',', '')) ?></b><?= $mr[1] ? '<small>/' . round($mr[1]) . '</small>' : '' ?></span><?php endif; ?>
      </a>
      <?php endif; endif; ?>
      <a class="top__help" href="<?= e(\App\Admin\Help::urlFor($meta['help'] ?? $nav)) ?>" title="Aide sur cet écran"><span aria-hidden="true">?</span> Aide</a>
      <?php // Bascule vers un autre musée : administrateurs seulement (les autres rôles ne savent rien des autres musées).
      $clubs = \App\Core\Auth::isAdmin() ? \App\Core\Auth::clubsOf($user ?? []) : []; if (count($clubs) > 1): $all = \App\Core\Club::all(); ?>
      <div class="me club-switch" data-dropdown>
        <button type="button" class="btn btn--sm" data-dropdown-toggle aria-expanded="false" title="Passer à un autre musée">
          <span class="club-switch__dot" style="background:<?= e(\App\Core\Club::color('navy')) ?>;border-color:<?= e(\App\Core\Club::color('yellow')) ?>"></span><?= e(\App\Core\Club::s('site')) ?> ▾
        </button>
        <div class="menu" hidden>
          <?php foreach ($clubs as $cid => $role): $p = $all[$cid] ?? []; $th = (array) ($p['theme'] ?? []) + \App\Core\Club::THEME; ?>
          <form method="post" action="/admin/basculer"><?= csrf_field() ?><input type="hidden" name="club" value="<?= e($cid) ?>">
            <button type="submit"<?= $cid === CLUB_ID ? ' disabled aria-current="true"' : '' ?>><span class="club-switch__dot" style="background:<?= e($th['navy']) ?>;border-color:<?= e($th['yellow']) ?>"></span><?= e((string) ($p['site'] ?? $cid)) ?><small><?= e($cid === CLUB_ID ? 'musée actuel' : (string) (($p['domains'] ?? [''])[0])) ?></small></button>
          </form>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <a class="top__site" href="/" target="_blank" rel="noopener">Site ↗</a>
      <div class="me" data-dropdown>
        <button type="button" data-dropdown-toggle aria-expanded="false" aria-label="Mon compte">
          <span class="avatar"><?= e(Base::initials((string) ($user['name'] ?? '?'))) ?></span>
          <span class="me__who"><b><?= e($user['name'] ?? '') ?></b><span><?= e($isAdmin ? 'Administrateur' : 'Utilisateur') ?></span></span>
        </button>
        <div class="menu" hidden>
          <a href="/admin/profil">Mon profil<small><?= e($user['email'] ?? '') ?></small></a>
          <form method="post" action="/admin/deconnexion"><?= csrf_field() ?><button type="submit" style="width:100%;box-sizing:border-box">Se déconnecter</button></form>
        </div>
      </div>
      <nav class="favs" aria-label="Mes favoris" data-favs>
        <span class="favs__star" aria-hidden="true">★</span>
        <span class="favs__list" data-favs-list><?php foreach ($favs as $f): ?><a href="<?= e($f['url']) ?>" class="favs__a<?= $here && $f['url'] === $here['url'] ? ' is-on' : '' ?>"<?= $here && $f['url'] === $here['url'] ? ' aria-current="page"' : '' ?>><?= e($f['label']) ?></a><?php endforeach; ?></span>
        <?php if (!$favs): ?><span class="favs__hint" data-favs-hint>Vos écrans les plus utilisés, à un clic :</span><?php endif; ?>
        <button type="button" class="favs__add" data-fav-open aria-haspopup="dialog">+ Ajouter un favori</button>
      </nav>
    </header>
    <?php if (!empty($meta['tabs'])): ?>
      <nav class="tabsbar" aria-label="Sections">
        <?php foreach ($meta['tabs'] as $t): ?><a href="<?= e($t[1]) ?>" class="<?= !empty($t[2]) ? 'is-on' : '' ?>"<?= !empty($t[2]) ? ' aria-current="page"' : '' ?>><?= e($t[0]) ?><?php if (isset($t[3]) && $t[3] !== ''): ?><em><?= e((string) $t[3]) ?></em><?php endif; ?></a><?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <main class="content" id="contenu">
      <?php foreach ($flash ?? [] as $f): ?><div class="toast<?= $f['type'] === 'error' ? ' toast--error' : '' ?>" data-toast role="status"><?= e($f['message']) ?></div><?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
</div>

<div class="qk" data-qk hidden role="dialog" aria-modal="true" aria-label="Recherche globale">
  <div class="qk__box">
    <input type="search" placeholder="Un match, un joueur, un média, une rubrique…" data-qk-input autocomplete="off" aria-label="Rechercher">
    <div class="qk__res" data-qk-res></div>
    <div class="qk__hint">↑ ↓ pour choisir · Entrée pour ouvrir · Échap pour fermer</div>
  </div>
</div>
<script type="application/json" id="bo-favs"><?= json_encode(['favs' => $favs, 'here' => $here, 'choices' => \App\Admin\Favorites::choices(), 'max' => \App\Admin\Favorites::MAX], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script type="application/json" id="bo-tips"><?= json_encode(['guide' => \App\Admin\Help::urlFor($meta['help'] ?? $nav), 'tips' => \App\Admin\Tips::forNav($meta['tips'] ?? $nav)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= asset('admin/admin.js') ?>" defer></script>
<script src="<?= asset('admin/wysiwyg.js') ?>" defer></script>
<script src="<?= asset('admin/correcteur.js') ?>" defer></script>
<script src="<?= asset('admin/verrou.js') ?>" defer></script>
<script src="<?= asset('admin/session.js') ?>" defer></script>
<?php foreach ($meta['scripts'] ?? [] as $js): ?><script src="<?= asset($js) ?>" defer></script><?php endforeach; ?>
</body>
</html>
