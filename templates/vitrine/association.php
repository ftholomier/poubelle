<?php
/** Qui sommes-nous. Variables : $p, $figures */
use App\Vitrine\Content;
use App\Vitrine\Host;
use App\Vitrine\Pages;
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => $p['title'], 'lead' => $p['lead'], 'eyebrow' => 'L’association', 'image' => Content::hasImage($p['image'] ?? null) ? $p['image'] : null, 'crumbs' => [['L’association', Host::url('/association/')]]]) ?>

<section class="section">
  <div class="wrap vcols">
    <div class="vcols__main" data-reveal>
      <h2 class="h-2"><?= e($p['story_title'] ?? '') ?></h2>
      <div class="prose mt-20"><?= Pages::rich($p['story'] ?? '') ?></div>
    </div>
    <aside class="vcols__side" data-reveal>
      <div class="vfacts">
        <?php foreach ($figures as $f): ?><div><b class="num" data-count><?= e($f['n']) ?></b><span><?= e($f['label']) ?> <?= e($f['text']) ?></span></div><?php endforeach; ?>
      </div>
    </aside>
  </div>
</section>

<section class="section bg-navy">
  <div class="wrap">
    <span class="eyebrow">Ce que nous faisons</span>
    <h2 class="h-section mt-20"><?= e($p['missions_title'] ?? '') ?></h2>
    <div class="vmissions mt-40">
      <?php foreach ($p['missions'] ?? [] as $m): ?>
        <div class="vmission" data-reveal><span class="vmission__i" aria-hidden="true"><?= e($m['icon'] ?? '★') ?></span><h3><?= e($m['title']) ?></h3><p><?= e($m['text']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <h2 class="h-section"><?= e($p['values_title'] ?? '') ?></h2>
    <div class="vvalues mt-40">
      <?php foreach ($p['values'] ?? [] as $i => $v): ?>
        <div class="vvalue" data-reveal><span class="vvalue__n"><?= pad2($i + 1) ?></span><h3><?= e($v['title']) ?></h3><p><?= e($v['text']) ?></p></div>
      <?php endforeach; ?>
    </div>
    <?php if (!empty($p['independence'])): ?><div class="prose vnote mt-40"><?= Pages::rich($p['independence']) ?></div><?php endif; ?>
  </div>
</section>

<section class="section--tight bg-sand bt">
  <div class="wrap vnext">
    <a class="vnext__card" href="<?= e(Host::url('/association/equipe/')) ?>"><span class="eyebrow">Les visages</span><b>L’équipe</b><span>Bureau et pôles de bénévoles →</span></a>
    <a class="vnext__card" href="<?= e(Host::url('/nos-actions/')) ?>"><span class="eyebrow">Sur le terrain</span><b>Nos actions</b><span>Musée, vidéos, archives, rencontres →</span></a>
    <a class="vnext__card vnext__card--y" href="<?= e(Host::url('/nous-soutenir/adherer/')) ?>"><span class="eyebrow">Rejoignez-nous</span><b>Adhérer</b><span>Devenez membre de l’association →</span></a>
  </div>
</section>
