<?php
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Services\Pros;

/** @var string $content @var array $pro @var string $section @var array $counts */
$nav = [
    'dashboard' => ['/espace-pro/', 'dashboard', 'Tableau de bord', 0],
    'fiche' => ['/espace-pro/fiche/', 'edit', 'Ma fiche', 0],
    'photos' => ['/espace-pro/photos/', 'camera', 'Photos & vidéos', 0],
    'requests' => ['/espace-pro/demandes/', 'inbox', 'Demandes de devis', $counts['requests'] ?? 0],
    'messages' => ['/espace-pro/messages/', 'mail', 'Messages', $counts['messages'] ?? 0],
    'reviews' => ['/espace-pro/avis/', 'star', 'Avis clients', $counts['reviews'] ?? 0],
    'stats' => ['/espace-pro/statistiques/', 'chart', 'Statistiques', 0],
    'account' => ['/espace-pro/compte/', 'settings', 'Mon compte', 0],
];
$status = $pro['status'] ?? 'pending';
$impersonating = Session::get('impersonate_from') !== null;
ob_start();
?>
<?php if ($impersonating): ?>
<div class="impersonate-bar">
  <span><?= icon('eye', 16) ?> Mode aperçu administrateur : vous voyez l'espace de <strong><?= e(Pros::displayName($pro)) ?></strong>.</span>
  <form method="post" action="/espace-pro/quitter-apercu"><?= csrf_field() ?><button class="btn btn-xs btn-yellow" type="submit">Quitter l'aperçu</button></form>
</div>
<?php endif; ?>
<div class="pro-shell">
  <aside class="pro-side" aria-label="Menu de l'espace pro">
    <div class="pro-me">
      <span class="pro-avatar" style="--c:<?= e(App\Services\Categories::color($pro['categories'][0] ?? null)) ?>">
        <?php $cover = null; foreach ((array) ($pro['photos'] ?? []) as $ph) { if (!empty($ph['cover'])) { $cover = $ph; } } $cover ??= ($pro['photos'][0] ?? null); ?>
        <?php if ($cover): ?><img src="<?= e(Pros::photo($cover, 'sm')) ?>" alt=""><?php else: ?><?= e(App\Core\Str::initials(Pros::displayName($pro))) ?><?php endif; ?>
      </span>
      <span class="pro-me-name"><b><?= e(Pros::displayName($pro)) ?></b>
        <span class="status-pill st-<?= e($status) ?>"><?= e(Pros::STATUSES[$status] ?? $status) ?></span></span>
    </div>
    <nav class="pro-nav">
      <?php foreach ($nav as $key => [$href, $ico, $label, $count]): ?>
        <a href="<?= e($href) ?>"<?= $section === $key ? ' class="on" aria-current="page"' : '' ?>><?= icon($ico, 18) ?><span><?= e($label) ?></span><?php if ($count > 0): ?><em class="pill-count"><?= $count > 99 ? '99+' : (int) $count ?></em><?php endif; ?></a>
      <?php endforeach; ?>
      <?php if ($status === 'active'): ?>
        <a href="<?= e(Url::pro($pro)) ?>" target="_blank" rel="noopener" class="ext"><?= icon('external', 18) ?><span>Voir ma fiche en ligne</span></a>
      <?php endif; ?>
    </nav>
    <form method="post" action="/deconnexion/" class="pro-logout"><?= csrf_field() ?><button type="submit" class="link"><?= icon('logout', 16) ?> Se déconnecter</button></form>
  </aside>
  <div class="pro-main">
    <?php if ($status === 'pending'): ?>
      <div class="alert alert-warning mb-2"><?= icon('clock', 18) ?><div><strong>Fiche en attente de validation.</strong> <?= empty($pro['email_verified']) ? 'Confirmez votre adresse email (lien envoyé à ' . e($pro['email']) . ') puis notre équipe publiera votre fiche.' : 'Notre équipe la vérifie et la publie très vite.' ?> Profitez-en pour la compléter !</div></div>
    <?php elseif ($status === 'suspended'): ?>
      <div class="alert alert-error mb-2"><?= icon('alert', 18) ?><div><strong>Fiche suspendue.</strong> Elle n'est plus visible sur le site. Contactez-nous via le <a href="/contact/">formulaire de contact</a> pour la réactiver.</div></div>
    <?php elseif ($status === 'inactive'): ?>
      <div class="alert alert-info mb-2"><?= icon('info', 18) ?><div><strong>Votre ancienne fiche n'est pas publiée.</strong> Bonne nouvelle : le site est désormais 100 % gratuit ! Complétez votre fiche puis <a href="/contact/?sujet=reactivation">demandez sa réactivation</a>.</div></div>
    <?php endif; ?>
    <?php if (!empty($pro['settings']['vacation'])): ?>
      <div class="alert alert-info mb-2"><?= icon('calendar', 18) ?><div><strong>Mode congés activé</strong> : vous ne recevez plus de demandes de devis groupées<?= !empty($pro['settings']['vacation_until']) ? ' jusqu\'au ' . e(date_fr($pro['settings']['vacation_until'], 'long')) : '' ?>. <a href="/espace-pro/compte/#preferences">Modifier</a></div></div>
    <?php endif; ?>
    <?= $content ?>
  </div>
</div>
<?php
$shell = (string) ob_get_clean();
echo View::partial('front/layout', ['content' => $shell, 'meta' => $meta ?? []]);
