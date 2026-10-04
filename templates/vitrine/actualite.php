<?php
/** Une actualité. Variables : $n, $others */
use App\Vitrine\Content;
use App\Vitrine\Host;
use App\Vitrine\Pages;
?>
<article>
  <header class="vart-head">
    <div class="wrap vart-head__in">
      <nav class="crumbs vcrumbs" aria-label="Fil d’Ariane"><a href="<?= e(Host::url('/')) ?>">Accueil</a><span aria-hidden="true">›</span><a href="<?= e(Host::url('/actualites/')) ?>">Actualités</a></nav>
      <span class="eyebrow"><time datetime="<?= e((string) ($n['date'] ?? '')) ?>"><?= e(date_fr((string) ($n['date'] ?? ''), true)) ?></time></span>
      <h1 class="vart-head__t"><?= e($n['title']) ?> <?= \App\Core\View::partial('vitrine/partials/verify', ['item' => $n]) ?></h1>
      <?php if (!empty($n['excerpt'])): ?><p class="vpage-head__lead"><?= e($n['excerpt']) ?></p><?php endif; ?>
    </div>
  </header>
  <?php if (Content::hasImage($n['image'] ?? null)): ?>
    <figure class="wrap vart-img"><img src="<?= e(img($n['image'], 1600)) ?>" srcset="<?= e(srcset($n['image'], [800, 1200, 1600])) ?>" sizes="(max-width: 1320px) 100vw, 1320px" alt=""></figure>
  <?php endif; ?>
  <div class="wrap vart-body">
    <div class="prose"><?= Pages::rich($n['body'] ?? '') ?></div>
    <div class="row gap-14 mt-40">
      <button type="button" class="btn btn--ghost btn--sm" data-share="<?= e(Host::abs('/actualites/' . $n['slug'] . '/')) ?>" data-share-title="<?= e($n['title']) ?>">Partager</button>
      <a class="btn btn--ghost btn--sm" href="<?= e(Host::url('/actualites/')) ?>">← Toutes les actualités</a>
    </div>
  </div>
</article>
<?php if ($others): ?>
<section class="section bg-sand bt">
  <div class="wrap">
    <h2 class="h-2">À lire aussi</h2>
    <div class="vnews-list vnews-list--3 mt-40"><?php foreach ($others as $o): ?><?= \App\Core\View::partial('vitrine/partials/news-card', ['n' => $o]) ?><?php endforeach; ?></div>
  </div>
</section>
<?php endif; ?>
