<?php
/** Index des face-à-face. Variables : $list */
$top = array_slice($list, 0, 12);
?>
<section class="hhead">
  <div class="wrap hhead__inner">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><a href="<?= e(url('/matchs/')) ?>"><?= e(t('Matchs')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t('Face-à-face')) ?></span></nav>
    <div class="stack" style="gap:12px">
      <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Face-à-face · historique complet')) ?></span>
      <h1 class="hhead__title">Sochaux <span class="yellow">×</span> …</h1>
      <p class="mhead__intro"><?= e(t('Choisissez un adversaire parmi les {n} clubs affrontés : bilan, buts, plus larges victoires, première rencontre.', ['n' => count($list)])) ?></p>
    </div>
    <div class="hhead__chips">
      <?php foreach ($top as $c): ?><a class="ychip" href="<?= e($c['href']) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
    </div>
  </div>
</section>
<div class="wrap mbody" data-oppsort>
  <div class="mtools__row">
    <div class="mtools__search">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/></svg>
      <label class="sr-only" for="oppq"><?= e(t('Chercher un adversaire')) ?></label>
      <input id="oppq" type="search" placeholder="<?= e(t('Chercher un adversaire…')) ?>" data-oppfilter autocomplete="off">
    </div>
    <div class="seg" role="group" aria-label="<?= e(t('Trier')) ?>">
      <button type="button" class="is-on" data-oppsortby="count"><?= e(t('Plus affrontés')) ?></button>
      <button type="button" data-oppsortby="name"><?= e(t('A–Z')) ?></button>
      <button type="button" data-oppsortby="ratio"><?= e(t('Meilleur bilan')) ?></button>
    </div>
  </div>
  <div class="opplist" data-opplist>
    <?php foreach ($list as $c): $tot = max(1, $c['v'] + $c['n'] + $c['d']); ?>
      <a class="opp" href="<?= e($c['href']) ?>" data-name="<?= e(\App\Data\Names::ascii($c['name'])) ?>" data-count="<?= (int) $c['count'] ?>" data-ratio="<?= round(($c['v'] + $c['n'] / 2) / $tot, 4) ?>">
        <span class="opp__name"><?= e($c['name']) ?></span>
        <span class="opp__n"><?= (int) $c['count'] ?> <?= e($c['count'] > 1 ? t('matchs') : t('match')) ?></span>
        <span class="vnd"><span class="vnd__v" style="width:<?= round(100 * $c['v'] / $tot, 1) ?>%"></span><span class="vnd__n" style="width:<?= round(100 * $c['n'] / $tot, 1) ?>%"></span><span class="vnd__d" style="width:<?= round(100 * $c['d'] / $tot, 1) ?>%"></span></span>
        <span class="opp__vnd"><?= (int) $c['v'] ?>V · <?= (int) $c['n'] ?>N · <?= (int) $c['d'] ?>D</span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
