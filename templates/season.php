<?php
/**
 * Page d'une saison (maquette « Saison ») : chiffres, fil de la saison, résultats, buteurs, effectif.
 * Variables : $season, $label, $matches, $sums, $comps, $scorers, $squad, $coaches, $bilan, $hero, $prev, $next, $current, $catPath
 */

use App\Front\Site;

$lineLabels = ['G' => t('Gardien'), 'D' => t('Défenseur'), 'M' => t('Milieu'), 'A' => t('Attaquant')];
?>
<section class="shero">
  <?php if ($hero): ?><div class="shero__bg"><img src="<?= e(img($hero, 1600)) ?>" srcset="<?= e(srcset($hero, [800, 1200, 1600])) ?>" sizes="100vw" alt="" fetchpriority="high"></div><?php endif; ?>
  <div class="wrap shero__inner">
    <div class="shero__nav">
      <?php if ($prev): ?><a href="<?= e(url('/matchs/' . $prev . '/')) ?>" rel="prev">← <?= e($prev) ?></a><?php else: ?><span></span><?php endif; ?>
      <span class="shero__kicker"><?= $current ? e(t('Saison en cours')) : e(t('Saison')) ?></span>
      <?php if ($next): ?><a href="<?= e(url('/matchs/' . $next . '/')) ?>" rel="next"><?= e($next) ?> →</a><?php else: ?><span></span><?php endif; ?>
    </div>
    <h1 class="shero__title"><?= e(substr($season, 0, 4)) ?><span class="yellow">–</span><?= e(substr($season, 7, 2)) ?></h1>
    <div class="shero__sums">
      <?php foreach ($sums as $s): ?>
        <div<?= !empty($s['title']) ? ' title="' . e($s['title']) . '"' : '' ?>><b<?= !empty($s['yellow']) ? ' class="yellow"' : '' ?>><?= e($s['v']) ?></b><span><?= e($s['k']) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<nav class="anchornav" data-anchornav aria-label="<?= e(t('Sommaire de la saison')) ?>">
  <div class="wrap anchornav__inner">
    <a href="#resultats"><?= e(t('Résultats')) ?></a>
    <?php if ($squad): ?><a href="#effectif"><?= e(t('Effectif')) ?></a><?php endif; ?>
    <?php if ($scorers): ?><a href="#buteurs"><?= e(t('Buteurs')) ?></a><?php endif; ?>
    <?php if ($bilan): ?><a href="<?= e(url($bilan['path'])) ?>"><?= e(t('Le bilan de la saison')) ?> →</a><?php endif; ?>
  </div>
</nav>

<div class="wrap sgrid">
  <section id="resultats" class="ssec">
    <h2 class="h-section"><?= e(t('Résultats')) ?></h2>
    <?php if ($matches): ?>
    <div class="sform" aria-label="<?= e(t('Le fil de la saison')) ?>">
      <?php foreach ($matches as $x): ?>
        <a class="sform__r res--<?= e($x['result'] ?: 'N') ?><?= !$x['result'] ? ' is-unknown' : '' ?>" href="<?= e(url($x['path'])) ?>" title="<?= e(date_num($x['date']) . ' · ' . Site::matchLabel($x)) ?>"><?= e($x['result'] ?: '?') ?></a>
      <?php endforeach; ?>
    </div>
    <?php if (count($comps) > 1): ?>
    <div class="mtools__chips" data-filterbar="results">
      <button type="button" class="mchip is-on" data-filter="*"><?= e(t('Toutes')) ?> <small><?= count($matches) ?></small></button>
      <?php foreach ($comps as $c => $n): ?><button type="button" class="mchip" data-filter="<?= e($c) ?>"><?= e(t($c)) ?> <small><?= (int) $n ?></small></button><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="mlist" data-filtered="results">
      <?php foreach ($matches as $x):
          $score = $x['us'] !== null ? ($x['sh'] ? $x['us'] . '-' . $x['them'] : $x['them'] . '-' . $x['us']) : '';
      ?>
      <a class="mrow" href="<?= e(url($x['path'])) ?>" data-key="<?= e($x['comp']) ?>">
        <span class="mrow__date"><?= e(date_num($x['date'])) ?></span>
        <span class="mrow__main"><span class="mrow__comp"><?= e(comp_round($x['label'] ?: $x['comp'], $x['round'])) ?></span><span class="mrow__teams"><?= e(trim($x['home'] . ' – ' . $x['away'], ' –') ?: ($x['event'] ?? $x['title'])) ?></span></span>
        <span class="mrow__score"><?= e($score) ?><?= !empty($x['extra']) ? ' <small>' . e($x['extra']) . '</small>' : '' ?></span>
        <?php if ($x['result']): ?><span class="res res--<?= e($x['result']) ?>"><?= e($x['result']) ?></span><?php else: ?><span></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div class="mempty"><span class="mempty__t"><?= e(t('Saison à écrire')) ?></span><span><?= e(t('Aucun match de cette saison n’est encore fiché dans le musée.')) ?></span><a class="btn btn--yellow btn--sm" href="<?= e(url('/contribuer/')) ?>"><?= e(t('Proposer des archives')) ?></a></div>
    <?php endif; ?>
    <span class="muted italic"><?= e(t('Résultats, effectif et buteurs générés automatiquement depuis les fiches matchs de la saison.')) ?></span>
  </section>

  <div class="ssec">
    <?php if ($bilan): ?>
    <a class="sbilan" href="<?= e(url($bilan['path'])) ?>">
      <span class="sbilan__img"><?php if ($bilan['image']): ?><img src="<?= e(img($bilan['image'], 480)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></span>
      <span class="sbilan__txt"><span class="eyebrow"><?= e(t('À lire')) ?></span><b><?= e($bilan['title']) ?></b><span><?= e(mb_strimwidth((string) $bilan['excerpt'], 0, 140, '…')) ?></span></span>
    </a>
    <?php endif; ?>
    <?php if ($scorers): ?>
    <section id="buteurs" class="ssec">
      <h2 class="h-section"><?= e(t('Buteurs')) ?></h2>
      <?php foreach ($scorers as $s): ?>
        <<?= $s['href'] ? 'a href="' . e($s['href']) . '"' : 'div' ?> class="scorer" data-reveal>
          <span class="scorer__main"><span class="scorer__name"><?= e($s['name']) ?></span><span class="scorer__bar"><span style="width:<?= $s['w'] ?>%"></span></span></span>
          <b><?= (int) $s['g'] ?></b>
        </<?= $s['href'] ? 'a' : 'div' ?>>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>
    <?php if ($coaches): ?>
    <section class="ssec">
      <h2 class="h-3"><?= e(count($coaches) > 1 ? t('Sur le banc') : t('Entraîneur')) ?></h2>
      <div class="coachlist">
        <?php foreach ($coaches as $c): ?>
          <<?= $c['href'] ? 'a href="' . e($c['href']) . '"' : 'div' ?> class="coach">
            <span class="coach__img"><?php if ($c['image']): ?><img src="<?= e(img($c['image'], 160)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></span>
            <span><b><?= e($c['name']) ?></b><small><?= (int) $c['n'] ?> <?= e(t('matchs')) ?></small></span>
          </<?= $c['href'] ? 'a' : 'div' ?>>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</div>

<?php if ($squad): ?>
<section id="effectif" class="ssquad">
  <div class="wrap ssquad__inner">
    <div class="between" style="align-items:flex-end;gap:16px;flex-wrap:wrap">
      <h2 class="h-section"><?= e(t('Effectif')) ?></h2>
      <span class="muted"><?= e(t('{n} joueurs utilisés', ['n' => count($squad)])) ?></span>
    </div>
    <div class="ssquad__grid">
      <?php foreach ($squad as $p): ?>
        <<?= $p['href'] ? 'a href="' . e($p['href']) . '"' : 'div' ?> class="sq">
          <span class="sq__img"><?php if ($p['image']): ?><img src="<?= e(img($p['image'], 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></span>
          <span class="sq__txt"><b><?= e($p['name']) ?></b><small><?= e($lineLabels[$p['line']] ?? '') ?> · <?= (int) $p['mj'] ?> <?= e(t('MJ')) ?><?= $p['goals'] ? ' · ' . (int) $p['goals'] . ' ' . e($p['goals'] > 1 ? t('buts') : t('but')) : '' ?></small></span>
        </<?= $p['href'] ? 'a' : 'div' ?>>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
