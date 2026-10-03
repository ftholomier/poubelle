<?php
/**
 * Face-à-face (maquette « Face à face ») et bilans par compétition ou par stade (même moteur).
 * Variables : $mode, $eyebrow, $titleHtml, $crumbs, $here, $chips, $allHref, $t, $highlights, $list, $groups, $groupBy, $comps, $shareImage
 */

use App\Front\Site;

$tones = ['yellow' => 'var(--yellow)', 'paper' => 'var(--paper)', 'sand' => 'var(--sand)'];
?>
<section class="hhead">
  <div class="wrap hhead__inner">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>">
      <?php foreach ($crumbs as $c): ?><a href="<?= e($c['href']) ?>"><?= e($c['label']) ?></a><span aria-hidden="true">/</span><?php endforeach; ?>
      <span aria-current="page"><?= e($here) ?></span>
    </nav>
    <div class="stack" style="gap:12px">
      <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e($eyebrow) ?></span>
      <h1 class="hhead__title"><?= $titleHtml ?></h1>
    </div>
    <?php if ($chips): ?>
    <div class="hhead__chips">
      <?php foreach ($chips as $c): ?><a class="ychip<?= $c['on'] ? ' is-on' : '' ?>" href="<?= e($c['href']) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
      <?php if ($allHref): ?><a class="ychip ychip--all" href="<?= e($allHref) ?>"><?= e(t('Tous les adversaires')) ?> →</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="hhead__stats">
      <div class="stack" style="gap:12px">
        <div class="vndbox">
          <div class="vndbox__v"><b data-count><?= (int) $t['V'] ?></b><span><?= e(t('Victoires')) ?></span></div>
          <div><b data-count><?= (int) $t['N'] ?></b><span><?= e(t('Nuls')) ?></span></div>
          <div><b data-count><?= (int) $t['D'] ?></b><span><?= e(t('Défaites')) ?></span></div>
        </div>
        <div class="vndbar"><span style="width:<?= $t['pV'] ?>%" class="vndbar__v"></span><span style="width:<?= $t['pN'] ?>%" class="vndbar__n"></span><span style="width:<?= $t['pD'] ?>%" class="vndbar__d"></span></div>
      </div>
      <div class="hhead__keys">
        <div><b><?= (int) $t['count'] ?></b><span><?= e(t('matchs joués')) ?></span></div>
        <div><b><?= (int) $t['gf'] ?></b><span><?= e(t('buts marqués')) ?></span></div>
        <div><b><?= (int) $t['ga'] ?></b><span><?= e(t('buts encaissés')) ?></span></div>
      </div>
    </div>
    <div class="row" style="gap:12px;flex-wrap:wrap;align-items:center">
      <span class="hhead__note"><?= e(t('Calculé automatiquement depuis les fiches matchs du musée')) ?></span>
      <button type="button" class="btn btn--ghost-light btn--sm" data-share><?= e(t('Partager')) ?></button>
    </div>
  </div>
</section>

<?php if ($highlights): ?>
<section class="wrap hcards">
  <?php foreach ($highlights as $h): ?>
    <a class="hcard" href="<?= e($h['href']) ?>" style="background:<?= $tones[$h['tone']] ?? 'var(--paper)' ?>" data-reveal>
      <span class="hcard__k"><?= e($h['k']) ?></span>
      <span class="hcard__score"><?= e($h['score']) ?></span>
      <span class="hcard__desc"><?= e($h['desc']) ?></span>
    </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (!empty($groups)): ?>
<section class="wrap hgroups">
  <h2 class="h-2"><?= e($groupBy === 'decade' ? t('Décennie par décennie') : t('Saison par saison')) ?></h2>
  <div class="dtable-wrap">
    <table class="dtable">
      <thead><tr><th scope="col"><?= e($groupBy === 'decade' ? t('Décennie') : t('Saison')) ?></th><th scope="col" class="n"><?= e(t('Matchs')) ?></th><th scope="col" class="n">V</th><th scope="col" class="n">N</th><th scope="col" class="n">D</th><th scope="col" class="n"><?= e(t('Buts')) ?></th><?php if ($groupBy !== 'decade' && $mode === 'comp'): ?><th scope="col"><?= e(t('Dernier match')) ?></th><?php endif; ?></tr></thead>
      <tbody>
        <?php foreach ($groups as $g): ?>
        <tr>
          <td class="strong"><?php if ($groupBy === 'decade'): ?><?= e(decade_label((int) $g['key'])) ?><?php else: ?><a href="<?= e(url('/matchs/' . $g['key'] . '/')) ?>"><?= e($g['key']) ?></a><?php endif; ?></td>
          <td class="n"><?= (int) $g['n'] ?></td><td class="n"><?= (int) $g['V'] ?></td><td class="n"><?= (int) $g['N'] ?></td><td class="n"><?= (int) $g['D'] ?></td>
          <td class="n"><?= (int) $g['gf'] ?>-<?= (int) $g['ga'] ?></td>
          <?php if ($groupBy !== 'decade' && $mode === 'comp'): $l = $g['last']; ?><td><a href="<?= e(url($l['path'])) ?>"><?= e(($l['round'] ? $l['round'] . ' · ' : '') . Site::matchLabel($l)) ?></a></td><?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<section class="wrap hlist">
  <div class="between" style="align-items:flex-end;gap:16px;flex-wrap:wrap">
    <h2 class="h-section"><?= e(t('Tous les matchs')) ?></h2>
    <div class="seg" role="group" data-filterbar="h2h" data-filter-attr="lieu">
      <button type="button" class="is-on" data-filter="*"><?= e(t('Tous')) ?></button>
      <button type="button" data-filter="dom"><?= e(t('Domicile')) ?></button>
      <button type="button" data-filter="ext"><?= e(t('Extérieur')) ?></button>
    </div>
  </div>
  <?php if (count($comps) > 1): ?>
  <div class="mtools__chips" data-filterbar="h2h" data-filter-attr="key">
    <button type="button" class="mchip is-on" data-filter="*"><?= e(t('Toutes')) ?></button>
    <?php foreach ($comps as $c => $n): ?><button type="button" class="mchip" data-filter="<?= e($c) ?>"><?= e(t($c)) ?> <small><?= (int) $n ?></small></button><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="mlist" data-filtered="h2h">
    <?php foreach ($list as $i => $x):
        $score = $x['us'] !== null ? ($x['sh'] ? $x['us'] . '-' . $x['them'] : $x['them'] . '-' . $x['us']) : '';
    ?>
    <a class="mrow" href="<?= e(url($x['path'])) ?>" data-key="<?= e($x['comp']) ?>" data-lieu="<?= $x['sh'] ? 'dom' : 'ext' ?>"<?= $i >= 40 ? ' data-more hidden' : '' ?>>
      <span class="mrow__date"><?= e(date_num($x['date'])) ?></span>
      <span class="mrow__main"><span class="mrow__comp"><?= e(comp_round($x['label'] ?: $x['comp'], $x['round'])) ?></span><span class="mrow__teams"><?= e(trim($x['home'] . ' – ' . $x['away'], ' –') ?: ($x['event'] ?? $x['title'])) ?></span></span>
      <span class="mrow__score"><?= e($score) ?><?= !empty($x['extra']) ? ' <small>' . e($x['extra']) . '</small>' : '' ?></span>
      <?php if ($x['result']): ?><span class="res res--<?= e($x['result']) ?>"><?= e($x['result']) ?></span><?php else: ?><span></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php if (count($list) > 40): ?><button type="button" class="btn btn--shadow" style="align-self:center" data-showall="h2h"><?= e(t('Afficher les {n} autres matchs', ['n' => count($list) - 40])) ?></button><?php endif; ?>
</section>
