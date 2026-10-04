<?php
/** Une action. Variables : $a, $others, $figures (musée), $videos, $channel, $centenary */
use App\Vitrine\Content;
use App\Vitrine\Host;
use App\Vitrine\Pages;

[$cta, $ctaExt] = Pages::link($a['cta_url'] ?? '');
[$cta2, $cta2Ext] = Pages::link($a['cta2_url'] ?? '');
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => $a['title'], 'lead' => $a['lead'] ?? '', 'eyebrow' => 'Nos actions', 'image' => Content::hasImage($a['image'] ?? null) ? $a['image'] : null, 'crumbs' => [['Nos actions', Host::url('/nos-actions/')], [$a['menu'] ?? $a['title'], Host::url('/nos-actions/' . $a['slug'] . '/')]]]) ?>

<?php if (\App\Vitrine\Site::$preview && !empty($a['a_verifier'])): ?>
<div class="wrap"><p class="alert mt-20"><b>À vérifier :</b> <?= e((string) ($a['a_verifier_note'] ?? 'contenu d’exemple.')) ?></p></div>
<?php endif; ?>

<section class="section">
  <div class="wrap vcols">
    <div class="vcols__main prose" data-reveal><?= Pages::rich($a['body'] ?? '') ?></div>
    <aside class="vcols__side">
      <div class="vbox vbox--sticky">
        <?php if ($cta !== ''): ?><a class="btn btn--yellow btn--block" href="<?= e($cta) ?>"<?= $ctaExt ? ' target="_blank" rel="noopener"' : '' ?>><?= e($a['cta_label'] ?? 'En savoir plus') ?><?= $ctaExt ? ' ↗' : '' ?></a><?php endif; ?>
        <?php if ($cta2 !== ''): ?><a class="btn btn--ghost btn--block mt-20" href="<?= e($cta2) ?>"<?= $cta2Ext ? ' target="_blank" rel="noopener"' : '' ?>><?= e($a['cta2_label'] ?? '') ?><?= $cta2Ext ? ' ↗' : '' ?></a><?php endif; ?>
        <?php if (!empty($a['countdown'])): ?>
          <p class="eyebrow mt-40" style="display:block">Jusqu’aux 100 ans du club</p>
          <div class="vcd-sm mt-20"><?= countdown_html($centenary) ?></div>
        <?php endif; ?>
        <?php if ($figures): ?>
          <div class="vfacts vfacts--box mt-40">
            <?php foreach (array_slice($figures, 2) as $f): ?><div><b class="num" data-count><?= e($f['n']) ?></b><span><?= e($f['label']) ?> <?= e($f['text']) ?></span></div><?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</section>

<?php if (!empty($a['videos'])): ?>
<section class="section bg-navy">
  <div class="wrap">
    <div class="vsec-head"><div><span class="eyebrow">YouTube</span><h2 class="h-section mt-20">Nos dernières vidéos</h2></div><?php if ($channel): ?><a class="link-under" href="<?= e($channel) ?>" target="_blank" rel="noopener">Toute la chaîne ↗</a><?php endif; ?></div>
    <?php if ($videos): ?>
      <div class="vvideos">
        <?php foreach ($videos as $v): ?>
          <a class="vvideo" href="<?= e($v['url']) ?>" target="_blank" rel="noopener" data-reveal>
            <span class="vvideo__img"><img src="<?= e(Host::url('/videos/miniature/' . $v['id'] . '.jpg')) ?>" alt="" loading="lazy" width="480" height="360"><span class="vvideo__play" aria-hidden="true">▶</span></span>
            <span class="vvideo__t"><?= e($v['title']) ?></span>
            <span class="vvideo__d"><?= e(date_fr($v['date'])) ?> · YouTube ↗</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="lead">Plus de 1 400 vidéos vous attendent sur notre chaîne.</p>
      <?php if ($channel): ?><a class="btn btn--yellow mt-20" href="<?= e($channel) ?>" target="_blank" rel="noopener">Voir la chaîne YouTube ↗</a><?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($others): ?>
<section class="section bg-sand bt">
  <div class="wrap">
    <h2 class="h-2">Nos autres actions</h2>
    <div class="vactions vactions--sm mt-40">
      <?php foreach (array_slice($others, 0, 3) as $o): ?><?= \App\Core\View::partial('vitrine/partials/action-card', ['a' => $o]) ?><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
