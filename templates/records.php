<?php
/** Livre des records (maquette « Records »). Variables : $cat, $title, $unit, $rows, $tabs, $decs, $compChips, $scope */
$podium = array_slice($rows, 0, 3);
$rest = array_slice($rows, 3);
$order = count($podium) === 3 ? [1, 0, 2] : array_keys($podium);
$rankLabel = fn (int $r) => $r === 1 ? t('1er') : t('{n}e', ['n' => $r]);
?>
<section class="rhead">
  <div class="wrap rhead__inner">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><a href="<?= e(url('/matchs/')) ?>"><?= e(t('Matchs')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t('Records')) ?></span></nav>
    <span class="eyebrow eyebrow--lg" style="color:var(--navy)"><?= e(t('Les records · calculés automatiquement')) ?></span>
    <h1 class="rhead__title"><?= e(t('Le livre')) ?><br><?= e(t('des records')) ?></h1>
    <nav class="rhead__tabs">
      <?php foreach ($tabs as $tb): ?><a href="<?= e($tb['href']) ?>"<?= $tb['on'] ? ' class="is-on" aria-current="page"' : '' ?>><?= e($tb['label']) ?></a><?php endforeach; ?>
    </nav>
  </div>
</section>
<div class="rfilters">
  <div class="wrap rfilters__inner">
    <div class="stack" style="gap:6px"><span class="rfilters__l"><?= e(t('Décennie')) ?></span><div class="rfilters__chips"><?php foreach ($decs as $d): ?><a class="rchip<?= $d['on'] ? ' is-on' : '' ?>" href="<?= e($d['href']) ?>" rel="nofollow"><?= e($d['label']) ?></a><?php endforeach; ?></div></div>
    <div class="stack" style="gap:6px"><span class="rfilters__l"><?= e(t('Compétition')) ?></span><div class="rfilters__chips"><?php foreach ($compChips as $d): ?><a class="rchip<?= $d['on'] ? ' is-on' : '' ?>" href="<?= e($d['href']) ?>" rel="nofollow"><?= e($d['label']) ?></a><?php endforeach; ?></div></div>
  </div>
</div>
<div class="wrap rbody">
  <div class="between" style="align-items:flex-end;gap:16px;flex-wrap:wrap">
    <div class="stack" style="gap:6px;flex:1 1 420px;min-width:0"><h2 class="h-2"><?= e($title) ?></h2><span class="muted" style="font-size:18px"><?= e($scope) ?></span></div>
    <button type="button" class="btn btn--shadow" data-share><?= e(t('Partager ce record')) ?></button>
  </div>
  <?php if (!$rows): ?>
    <div class="mempty"><span class="mempty__t"><?= e(t('Pas encore de record')) ?></span><span><?= e(t('Aucune fiche ne correspond à ces filtres pour le moment.')) ?></span></div>
  <?php else: ?>
  <div class="podium podium--<?= count($podium) ?>">
    <?php foreach ($order as $i): $p = $podium[$i]; $r = $i + 1; ?>
      <a class="podium__p podium__p--<?= $r ?>" href="<?= e($p['href']) ?>">
        <span class="podium__img"><?php if ($p['image']): ?><img src="<?= e(img($p['image'], 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></span>
        <span class="podium__name"><?= e($p['name']) ?></span>
        <span class="podium__step"><b><?= e($p['v']) ?></b><small><?= e($rankLabel($r)) ?> · <?= e($unit) ?></small></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if ($rest): ?>
  <ol class="rlist" start="4">
    <?php foreach ($rest as $k => $row): ?>
      <li><a href="<?= e($row['href']) ?>"><span class="rlist__rank"><?= pad2($k + 4) ?></span><span class="rlist__main"><b><?= e($row['name']) ?></b><small><?= e($row['meta']) ?></small></span><span class="rlist__v"><?= e($row['v']) ?></span></a></li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>
  <?php endif; ?>
  <span class="muted italic"><?= e(t('Classements recalculés à chaque nouvelle fiche publiée. Seuls les joueurs reliés à leur fiche sont comptés.')) ?></span>
</div>
