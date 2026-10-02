<?php
/** Toutes les saisons, par décennie. Variables : $byDecade, $current */
?>
<section class="mhead">
  <div class="wrap mhead__inner" style="padding-bottom:clamp(32px,4vw,56px)">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><a href="<?= e(url('/matchs/')) ?>"><?= e(t('Matchs')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t('Saisons')) ?></span></nav>
    <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Depuis 1928')) ?></span>
    <h1 class="mhead__title"><?= e(t('Saisons')) ?></h1>
    <p class="mhead__intro"><?= e(t('Choisissez une saison : résultats, effectif et buteurs, calculés depuis les fiches matchs.')) ?></p>
  </div>
</section>
<div class="wrap mbody">
  <?php foreach ($byDecade as $decade => $list): ?>
  <section class="sdec">
    <h2 class="h-3"><?= e(t('Années')) ?> <?= $decade < 2000 ? e(substr((string) $decade, 2)) : (int) $decade ?></h2>
    <div class="sdec__grid">
      <?php foreach (array_reverse($list) as $s): $tot = max(1, $s['res']['V'] + $s['res']['N'] + $s['res']['D']); ?>
        <a class="scard<?= $s['season'] === $current ? ' is-current' : '' ?><?= !$s['n'] ? ' is-empty' : '' ?>" href="<?= e($s['href']) ?>">
          <span class="scard__y"><?= e(str_replace('-', '–', $s['season'])) ?></span>
          <span class="scard__m"><?= $s['n'] ? (int) $s['n'] . ' ' . e(t('matchs')) : e(t('à écrire')) ?><?= $s['division'] ? ' · ' . e(\App\Front\Explore::shortDivision($s['division'])) : '' ?></span>
          <?php if ($s['n']): ?><span class="vnd" aria-label="<?= e($s['res']['V'] . ' V, ' . $s['res']['N'] . ' N, ' . $s['res']['D'] . ' D') ?>"><span class="vnd__v" style="width:<?= round(100 * $s['res']['V'] / $tot, 1) ?>%"></span><span class="vnd__n" style="width:<?= round(100 * $s['res']['N'] / $tot, 1) ?>%"></span><span class="vnd__d" style="width:<?= round(100 * $s['res']['D'] / $tot, 1) ?>%"></span></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>
</div>
