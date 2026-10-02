<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;
use App\Core\Url;
use App\Services\AdminStats;
use App\Services\Settings;

/** @var string $content @var string $title @var string $section */
$me = Auth::admin();
$counts = $me ? AdminStats::counts() : [];
$can = static fn (string $p): bool => Auth::adminCan($p);
$groups = [
    'Pilotage' => [
        ['dashboard', '', 'dashboard', 'Tableau de bord', 0, 'dashboard'],
        ['stats', 'statistiques', 'chart', 'Statistiques', 0, 'dashboard'],
        ['notifications', 'notifications', 'bell', 'Notifications', $counts['notifications'] ?? 0, 'notifications'],
    ],
    'Annuaire' => [
        ['pros', 'pros', 'users', 'Pros', $counts['pros'] ?? 0, 'pros'],
        ['requests', 'demandes', 'inbox', 'Demandes de devis', $counts['requests'] ?? 0, 'requests'],
        ['messages', 'messages', 'mail', 'Messages aux pros', $counts['messages'] ?? 0, 'messages'],
        ['contacts', 'contacts', 'chat', 'Formulaire de contact', $counts['contacts'] ?? 0, 'messages'],
        ['reviews', 'avis', 'star', 'Avis', $counts['reviews'] ?? 0, 'reviews'],
    ],
    'Communication' => [
        ['mailing', 'emailing', 'megaphone', 'Emailing', 0, 'mailing'],
        ['templates', 'emailing/modeles', 'file', 'Modèles d\'emails', 0, 'mailing'],
        ['queue', 'emailing/file', 'send', 'File d\'envoi', $counts['queue_failed'] ?? 0, 'mailing'],
        ['prospects', 'prospects', 'database', 'Prospects', 0, 'mailing'],
    ],
    'Contenu' => [
        ['blog', 'blog', 'edit', 'Blog', 0, 'content'],
        ['pages', 'pages', 'file', 'Pages', 0, 'content'],
        ['home', 'accueil', 'home', 'Page d\'accueil', 0, 'content'],
        ['categories', 'categories', 'tag', 'Métiers', 0, 'content'],
        ['occasions', 'occasions', 'party', 'Occasions', 0, 'content'],
    ],
    'Référencement' => [
        ['seo', 'seo', 'search', 'Titres & descriptions', 0, 'seo'],
        ['landings', 'seo/pages-locales', 'map', 'Pages locales', 0, 'seo'],
        ['redirects', 'seo/redirections', 'link', 'Redirections & 404', 0, 'seo'],
        ['robots', 'seo/robots', 'robot', 'robots.txt', 0, 'seo'],
    ],
    'Réglages' => [
        ['ads', 'publicite', 'euro', 'Publicité AdSense', 0, 'settings'],
        ['features', 'reglages/fonctionnement', 'settings', 'Fonctionnement', 0, 'settings'],
        ['notif-settings', 'reglages/notifications', 'bell', 'Alertes admin', 0, 'settings'],
        ['antispam', 'antispam', 'shield', 'Anti-spam', 0, 'settings'],
        ['ai', 'ia', 'sparkles', 'Intelligence artificielle', 0, 'settings'],
        ['env', 'reglages', 'key', 'Configuration (.env)', 0, 'env'],
    ],
    'Système' => [
        ['system', 'maintenance', 'database', 'Maintenance & import', 0, 'system'],
        ['logs', 'journal', 'list', 'Journal', 0, 'system'],
        ['archives', 'archives', 'archive', 'Archives', 0, 'system'],
        ['users', 'utilisateurs', 'lock', 'Utilisateurs', 0, 'users'],
        ['memo', 'memo', 'clock', 'Mémo', 0, 'dashboard'],
    ],
];
$config = [
    'csrf' => Csrf::token(),
    'admin' => Url::admin(),
    'vapid' => App\Services\Push::publicKey(),
];
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'Administration') ?> — Back-office</title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="manifest" href="/manifest.webmanifest?app=admin" crossorigin="use-credentials">
<meta name="theme-color" content="#1c1233">
<link rel="preload" href="/assets/fonts/bricolage-grotesque-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<script type="application/json" id="apvs-config"><?= js($config) ?></script>
</head>
<body class="adm">
<aside class="adm-side" id="adm-side" aria-label="Menu du back-office">
  <a class="adm-logo" href="<?= e(Url::admin()) ?>"><span class="logo-mark" aria-hidden="true">A</span><span><b>APVS</b><small>Back-office</small></span></a>
  <nav class="adm-nav">
    <?php foreach ($groups as $gLabel => $items): $visible = array_filter($items, static fn ($i) => $can($i[5])); if (!$visible) { continue; } ?>
      <p class="adm-nav-title"><?= e($gLabel) ?></p>
      <?php foreach ($visible as [$key, $path, $ico, $label, $count, $perm]): ?>
        <a href="<?= e(Url::admin($path)) ?>"<?= ($section ?? '') === $key ? ' class="on" aria-current="page"' : '' ?>><?= icon($ico, 17) ?><span><?= e($label) ?></span><?php if ($count > 0): ?><em class="pill-count"><?= $count > 99 ? '99+' : (int) $count ?></em><?php endif; ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
</aside>
<div class="adm-wrap">
  <header class="adm-top">
    <button type="button" class="icon-btn adm-burger" data-adm-burger aria-label="Menu" aria-controls="adm-side"><?= icon('menu', 18) ?></button>
    <button type="button" class="adm-search" data-palette-open><?= icon('search', 16) ?><span>Rechercher un pro, une demande, un email…</span><kbd>Ctrl K</kbd></button>
    <div class="adm-top-right">
      <a class="icon-btn" href="/" target="_blank" rel="noopener" title="Voir le site" aria-label="Voir le site"><?= icon('external', 17) ?></a>
      <div class="adm-drop">
        <button type="button" class="icon-btn adm-bell" data-notif-toggle aria-label="Notifications" aria-expanded="false"><?= icon('bell', 17) ?><?php if (($counts['notifications'] ?? 0) > 0): ?><span class="dot-count" data-notif-count><?= (int) $counts['notifications'] > 99 ? '99+' : (int) $counts['notifications'] ?></span><?php endif; ?></button>
        <div class="adm-panel hidden" data-notif-panel><div class="adm-panel-head"><b>Notifications</b><form method="post" action="<?= e(Url::admin('notifications/lues')) ?>"><?= csrf_field() ?><button class="link small" type="submit">Tout marquer lu</button></form></div><div data-notif-list><p class="muted small">Chargement…</p></div><a class="adm-panel-foot" href="<?= e(Url::admin('notifications')) ?>">Toutes les notifications</a></div>
      </div>
      <div class="adm-drop">
        <button type="button" class="adm-user" data-menu-toggle aria-expanded="false"><span class="adm-avatar"><?= e(App\Core\Str::initials((string) (($me['name'] ?? '') ?: ($me['email'] ?? '?')))) ?></span><span class="adm-user-name"><?= e((string) (($me['name'] ?? '') ?: ($me['email'] ?? ''))) ?></span><?= icon('chevron-down', 14) ?></button>
        <div class="adm-panel adm-menu hidden" data-menu-panel>
          <a href="<?= e(Url::admin('mon-compte')) ?>"><?= icon('user', 16) ?> Mon compte</a>
          <a href="<?= e(Url::admin('mon-compte#2fa')) ?>"><?= icon('lock', 16) ?> Double authentification</a>
          <button type="button" data-push-subscribe="<?= e(Url::admin('push/subscribe')) ?>"><?= icon('bell', 16) ?> Alertes sur cet appareil</button>
          <form method="post" action="<?= e(Url::admin('logout')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('logout', 16) ?> Déconnexion</button></form>
        </div>
      </div>
    </div>
  </header>
  <main class="adm-main" id="contenu">
    <?php $flashes = Session::flashes(); if ($flashes): ?>
      <div class="adm-flashes" role="status"><?php foreach ($flashes as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if (App\Core\Env::bool('MAINTENANCE_MODE')): ?><div class="alert alert-warning mb-2"><?= icon('alert', 18) ?> Le site public est en mode maintenance. <a href="<?= e(Url::admin('reglages')) ?>">Désactiver</a></div><?php endif; ?>
    <?= $content ?>
  </main>
</div>
<dialog class="palette" id="palette" aria-label="Recherche">
  <div class="palette-box">
    <div class="palette-input"><?= icon('search', 18) ?><input type="search" placeholder="Pro, demande, email, téléphone, ville, page…" aria-label="Rechercher" autocomplete="off"><kbd>Échap</kbd></div>
    <ul class="palette-list" role="listbox"></ul>
  </div>
</dialog>
<div class="toast-zone" aria-live="polite"></div>
<script src="<?= asset('js/app.js') ?>" defer<?= Security::attr() ?>></script>
<script src="<?= asset('js/editor.js') ?>" defer<?= Security::attr() ?>></script>
<?php foreach ($scripts ?? [] as $s): ?><script src="<?= e($s) ?>" defer<?= Security::attr() ?>></script><?php endforeach; ?>
<script src="<?= asset('js/admin.js') ?>" defer<?= Security::attr() ?>></script>
</body>
</html>
