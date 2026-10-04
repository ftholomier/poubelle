<?php
/** Un événement de l'association. Variables : $e, $next */
use App\Vitrine\Content;
use App\Vitrine\Host;
use App\Vitrine\Pages;
use App\Vitrine\Site;

[$link, $linkExt] = Pages::link($e['link'] ?? '');
$past = ($e['end'] !== '' ? $e['end'] : $e['start']) < date('Y-m-d H:i');
?>
<article>
  <header class="vart-head">
    <div class="wrap vart-head__in">
      <nav class="crumbs vcrumbs" aria-label="Fil d’Ariane"><a href="<?= e(Host::url('/')) ?>">Accueil</a><span aria-hidden="true">›</span><a href="<?= e(Host::url('/agenda/')) ?>">Agenda</a></nav>
      <span class="eyebrow"><?= $past ? 'Événement passé' : 'Rendez-vous' ?></span>
      <h1 class="vart-head__t"><?= e($e['title']) ?> <?= \App\Core\View::partial('vitrine/partials/verify', ['item' => $e]) ?></h1>
      <?php if (!empty($e['excerpt'])): ?><p class="vpage-head__lead"><?= e($e['excerpt']) ?></p><?php endif; ?>
    </div>
  </header>
  <div class="wrap vcols" style="padding-bottom:var(--section)">
    <div class="vcols__main">
      <?php if (Content::hasImage($e['image'] ?? null)): ?><img class="vevent-img" src="<?= e(img($e['image'], 1200)) ?>" srcset="<?= e(srcset($e['image'], [800, 1200, 1600])) ?>" sizes="(max-width: 900px) 100vw, 60vw" alt=""><?php endif; ?>
      <div class="prose mt-40"><?= Pages::rich($e['body'] ?? '') ?></div>
    </div>
    <aside class="vcols__side">
      <dl class="vbox vinfo">
        <div><dt>Quand</dt><dd><?= e(Site::when($e)) ?></dd></div>
        <?php if (!empty($e['place'])): ?><div><dt>Où</dt><dd><?= e($e['place']) ?><?php if (!empty($e['address'])): ?><br><span class="muted"><?= e($e['address']) ?></span><?php endif; ?></dd></div><?php endif; ?>
        <?php if (!empty($e['price'])): ?><div><dt>Tarif</dt><dd><?= e($e['price']) ?></dd></div><?php endif; ?>
      </dl>
      <?php if ($link !== '' && !$past): ?><a class="btn btn--yellow btn--block mt-20" href="<?= e($link) ?>"<?= $linkExt ? ' target="_blank" rel="noopener"' : '' ?>><?= e($e['link_label'] ?? 'S’inscrire') ?><?= $linkExt ? ' ↗' : '' ?></a><?php endif; ?>
      <a class="btn btn--ghost btn--block mt-20" href="<?= e(Host::url('/agenda/agenda.ics')) ?>">Ajouter à mon agenda</a>
    </aside>
  </div>
</article>
<?php if ($next): ?>
<section class="section bg-sand bt">
  <div class="wrap"><h2 class="h-2">Les prochains rendez-vous</h2><div class="vevents mt-40"><?php foreach ($next as $x): ?><?= \App\Core\View::partial('vitrine/partials/event-row', ['e' => $x]) ?><?php endforeach; ?></div></div>
</section>
<?php endif; ?>
