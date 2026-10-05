<?php
/**
 * La grande mosaïque : chaque case est une photo, teintée de jaune si elle dessine le motif, de
 * bleu sinon ; survolée, elle s'agrandit en couleurs. Une grille large (ordinateur) et une
 * étroite (téléphone), avec les mêmes photos. Variables : $wide, $narrow, $photos, $motif,
 * $motifs, $credits
 */
use App\Front\Walls;

$tiles = function (array $grid) use ($photos): string {
    $rows = count($grid);
    $cols = count($grid[0] ?? []);
    $html = '';
    $k = 0;
    foreach ($grid as $y => $row) {
        foreach ($row as $x => $on) {
            $p = $photos[$k] ?? null;
            if (!$p) {
                $html .= '<span class="mo__t mo__t--empty' . ($on ? ' is-on' : '') . '"></span>';
                $k++;
                continue;
            }
            $d = (int) round(abs($x - ($cols - 1) / 2) + abs($y - ($rows - 1) / 2));
            $tip = '© ' . $p['credit'] . ($p['title'] !== '' ? ' · ' . $p['title'] : '');
            $html .= '<button type="button" tabindex="-1" class="mo__t' . ($on ? ' is-on' : '') . '" style="--d:' . $d . '" title="' . e($tip) . '" ' . Walls::attrs($p, $k) . '>'
                . '<img src="' . e($p['src160']) . '" alt="" width="160" height="160" loading="lazy" decoding="async"></button>';
            $k++;
        }
    }
    return $html;
};
?>
<div class="mo" data-mosaic>
  <div class="mo__bar">
    <span class="mo__legend"><?= e(t('Le motif')) ?> : <b><?= e($motifs[$motif] ?? '') ?></b></span>
    <button type="button" class="btn btn--sm btn--ghost-light mo__btn" data-mo-reveal aria-pressed="false"><?= e(t('Voir les photos en couleurs')) ?></button>
    <button type="button" class="btn btn--sm btn--ghost-light mo__btn" data-mo-first><?= e(t('Les regarder une à une')) ?></button>
  </div>
  <?php if (!$photos): ?><p class="wempty"><?= e(t('Aucune photo pour ce choix : essayez une autre décennie ou un autre photographe.')) ?></p><?php else: ?>
  <div class="mo__grid mo__grid--wide" style="--cols:<?= count($wide[0] ?? []) ?>"><?= $tiles($wide) ?></div>
  <div class="mo__grid mo__grid--narrow" style="--cols:<?= count($narrow[0] ?? []) ?>"><?= $tiles($narrow) ?></div>
  <?php
  // Les dix crédits les plus présents, les autres dépliables : la liste complète serait trop longue.
  $list = fn (array $c): string => implode(', ', array_map(fn ($who, $n) => $who . ' (' . $n . ')', array_keys($c), $c));
  $more = array_slice($credits, 10, null, true);
  ?>
  <div class="mo__credits">
    <p><b><?= e(t('Crédits photo de cette mosaïque')) ?> :</b> <?= e($list(array_slice($credits, 0, 10, true))) ?><?= $more ? '…' : '.' ?></p>
    <?php if ($more): ?>
      <details><summary><?= e(t('et {n} autres photographes ou sources', ['n' => count($more)])) ?></summary><p><?= e($list($more)) ?>.</p></details>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
