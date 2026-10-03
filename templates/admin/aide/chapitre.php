<?php
/** Aide : un chapitre. Variables : $c, $prev, $next, $chapters */
use App\Admin\Help;

$n = array_search($c['slug'], array_keys($chapters), true) + 1;
?>
<div class="aide-layout">
  <article class="aide-doc">
    <header class="aide-doc__head">
      <span class="aide-kicker">Chapitre <?= sprintf('%02d', $n) ?> · <a href="/admin/aide">Guide d’utilisation</a></span>
      <h2><?= e($c['title']) ?></h2>
      <p class="aide-lead"><?= e($c['summary']) ?></p>
    </header>
    <?php foreach ($c['sections'] as $s): ?>
      <section class="aide-sec" id="<?= e($s['id']) ?>">
        <h3><a href="#<?= e($s['id']) ?>" class="aide-anchor" aria-label="Lien vers cette partie">#</a><?= e($s['title']) ?></h3>
        <?= Help::render($s['html']) ?>
      </section>
    <?php endforeach; ?>
    <nav class="aide-pager" aria-label="Chapitres voisins">
      <?php if ($prev): ?><a href="/admin/aide/<?= e($prev['slug']) ?>"><small>← Chapitre précédent</small><b><?= e($prev['title']) ?></b></a><?php else: ?><span></span><?php endif; ?>
      <?php if ($next): ?><a class="aide-pager__next" href="/admin/aide/<?= e($next['slug']) ?>"><small>Chapitre suivant →</small><b><?= e($next['title']) ?></b></a><?php endif; ?>
    </nav>
  </article>
  <aside class="aide-toc">
    <div class="aide-toc__box">
      <b>Dans ce chapitre</b>
      <?php foreach ($c['sections'] as $s): ?><a href="#<?= e($s['id']) ?>"><?= e($s['title']) ?></a><?php endforeach; ?>
    </div>
    <div class="aide-toc__box aide-toc__box--all">
      <b>Chapitres</b>
      <?php $i = 0; foreach ($chapters as $x): $i++; ?><a href="/admin/aide/<?= e($x['slug']) ?>"<?= $x['slug'] === $c['slug'] ? ' class="is-on" aria-current="page"' : '' ?>><em><?= sprintf('%02d', $i) ?></em> <?= e($x['title']) ?></a><?php endforeach; ?>
    </div>
    <a class="btn btn--sm btn--block" href="/admin/aide/imprimer#<?= e($c['slug']) ?>" target="_blank">Version imprimable</a>
  </aside>
</div>
