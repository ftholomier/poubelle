<?php
/** Bilan d'un carnet (page personnelle et page publique). Variables : $s (Carnet::stats), $mine (bool : boutons pour retirer) */
$pct = fn (int $n) => $s['n'] ? (int) round(100 * $n / $s['n']) : 0;
?>
<div class="cnkpis">
  <div class="cnkpi cnkpi--big"><b><?= (int) $s['n'] ?></b><span><?= e(t($s['n'] > 1 ? 'matchs vus au stade' : 'match vu au stade')) ?></span><?php if ($s['first']): ?><small><?= e(substr((string) $s['first']['date'], 0, 4)) ?> → <?= e(substr((string) $s['last']['date'], 0, 4)) ?></small><?php endif; ?></div>
  <div class="cnkpi"><b><?= (int) $s['v'] ?></b><span><?= e(t('victoires')) ?></span><small><?= $pct($s['v']) ?> %</small></div>
  <div class="cnkpi"><b><?= (int) $s['nul'] ?></b><span><?= e(t('nuls')) ?></span></div>
  <div class="cnkpi"><b><?= (int) $s['d'] ?></b><span><?= e(t('défaites')) ?></span></div>
  <div class="cnkpi"><b><?= (int) $s['gf'] ?></b><span><?= e(t('buts du FCSM vus')) ?></span><small><?= e(t('{n} encaissés', ['n' => $s['ga']])) ?></small></div>
  <div class="cnkpi"><b><?= (int) $s['home'] ?></b><span><?= e(t('à domicile')) ?></span><small><?= e(t('{n} en déplacement', ['n' => $s['away']])) ?></small></div>
</div>
<?php if ($s['n']): ?>
<div class="cnbar" role="img" aria-label="<?= e($s['v'] . ' V, ' . $s['nul'] . ' N, ' . $s['d'] . ' D') ?>"><i class="v" style="width:<?= $pct($s['v']) ?>%"></i><i class="n" style="width:<?= $pct($s['nul']) ?>%"></i><i class="d" style="width:<?= $pct($s['d']) ?>%"></i></div>
<?php endif; ?>

<?php if ($s['luck']): $l = $s['luck']; ?>
<section class="cnluck <?= $l['diff'] >= 0 ? 'is-good' : '' ?>">
  <span class="eyebrow"><?= e(t('Porte-bonheur ?')) ?></span>
  <p><b><?= e(($l['diff'] >= 0 ? '+' : '−') . abs($l['diff'])) ?> <?= e(t('points')) ?></b> — <?= e(t('{m} % de victoires dans les matchs vus, contre {c} % pour le club sur les mêmes saisons.', ['m' => $l['mine'], 'c' => $l['club']])) ?>
  <?= e($l['diff'] >= 5 ? t('Le club gagne plus souvent quand vous êtes là !') : ($l['diff'] <= -5 ? t('Le club a souffert sous vos yeux… mais vous êtes resté fidèle.') : t('Ni chat noir ni trèfle : un supporter fidèle.'))) ?></p>
</section>
<?php endif; ?>

<div class="cngrid">
  <?php if ($s['best'] || $s['first']): ?>
  <section class="cncard">
    <h2 class="cncard__t"><?= e(t('Les dates')) ?></h2>
    <dl class="cndl">
      <?php if ($s['first']): ?><dt><?= e(t('Premier match')) ?></dt><dd><a href="<?= e(url($s['first']['path'])) ?>"><?= e($s['first']['title']) ?></a></dd><?php endif; ?>
      <?php if ($s['last'] && $s['n'] > 1): ?><dt><?= e(t('Dernier match')) ?></dt><dd><a href="<?= e(url($s['last']['path'])) ?>"><?= e($s['last']['title']) ?></a></dd><?php endif; ?>
      <?php if ($s['best']): ?><dt><?= e(t('Plus belle victoire')) ?></dt><dd><a href="<?= e(url($s['best']['path'])) ?>"><?= e($s['best']['title']) ?></a></dd><?php endif; ?>
    </dl>
  </section>
  <?php endif; ?>
  <?php if ($s['opps']): ?>
  <section class="cncard">
    <h2 class="cncard__t"><?= e(t('Adversaires les plus vus')) ?></h2>
    <ol class="cnrank"><?php foreach (array_slice($s['opps'], 0, 5, true) as $o => $n): ?><li><span><?= e($o) ?></span><b><?= (int) $n ?></b></li><?php endforeach; ?></ol>
  </section>
  <?php endif; ?>
  <?php if ($s['scorers']): ?>
  <section class="cncard">
    <h2 class="cncard__t"><?= e(t('Buteurs vus en action')) ?></h2>
    <ol class="cnrank"><?php foreach (array_slice($s['scorers'], 0, 5, true) as $p => $n): ?><li><span><?= e($p) ?></span><b><?= (int) $n ?></b></li><?php endforeach; ?></ol>
  </section>
  <?php endif; ?>
  <?php if ($s['players']): ?>
  <section class="cncard">
    <h2 class="cncard__t"><?= e(t('Joueurs les plus vus')) ?></h2>
    <ol class="cnrank"><?php foreach (array_slice($s['players'], 0, 5) as $p): ?><li><span><?php if ($p['path'] !== ''): ?><a href="<?= e(url($p['path'])) ?>"><?= e($p['name']) ?></a><?php else: ?><?= e($p['name']) ?><?php endif; ?></span><b><?= (int) $p['n'] ?></b></li><?php endforeach; ?></ol>
    <p class="cnnote"><?= e(t('D’après les compositions connues du musée (incomplètes pour les matchs anciens).')) ?></p>
  </section>
  <?php endif; ?>
  <?php if ($s['decades']): $max = max($s['decades']); ?>
  <section class="cncard">
    <h2 class="cncard__t"><?= e(t('Par décennie')) ?></h2>
    <ul class="cndec"><?php foreach ($s['decades'] as $d => $n): ?><li><span><?= (int) $d ?></span><i style="width:<?= (int) round(100 * $n / $max) ?>%"></i><b><?= (int) $n ?></b></li><?php endforeach; ?></ul>
  </section>
  <?php endif; ?>
</div>

<section class="cnbadges" aria-labelledby="cn-badges">
  <h2 class="h-section" id="cn-badges"><?= e(t('Badges')) ?> <small><?= count(array_filter($s['badges'], fn ($b) => $b['on'])) ?>/<?= count($s['badges']) ?></small></h2>
  <ul class="cnbadges__list">
    <?php foreach ($s['badges'] as $b): ?><li class="cnbadge<?= $b['on'] ? ' is-on' : '' ?>" title="<?= e($b['d']) ?>"><span class="cnbadge__i" aria-hidden="true"><?= e($b['icon']) ?></span><b><?= e($b['label']) ?></b><small><?= e($b['d']) ?></small></li><?php endforeach; ?>
  </ul>
</section>

<?php if ($s['list']): ?>
<section class="cnlist" aria-labelledby="cn-list">
  <h2 class="h-section" id="cn-list"><?= e(t('Les matchs')) ?></h2>
  <ul>
    <?php foreach ($s['list'] as $x): ?>
      <li data-cn-row="<?= (int) $x['id'] ?>"><span class="cnres cnres--<?= e(strtolower((string) $x['result'])) ?>"><?= e($x['result']) ?></span><a href="<?= e(url($x['path'])) ?>"><?= e($x['title']) ?></a><?php if (!empty($mine)): ?><button type="button" class="linkbtn" data-cn-remove="<?= (int) $x['id'] ?>" aria-label="<?= e(t('Retirer ce match de mon carnet')) ?>">✕</button><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
