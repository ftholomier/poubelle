<?php
/**
 * Éléments d'une mosaïque (aussi renvoyés en fragment pour « Afficher plus »).
 * Variables : $items (résumés d'index), $kind (matchs|lions|articles), $view (grid|list), $offset, $catSlug
 */

use App\Data\Categories;
use App\Front\Pages;

$pattern = [[2, 2], [1, 1], [1, 2], [1, 1], [2, 1], [1, 1], [1, 1], [1, 2]];
$lineLabels = ['G' => t('Gardien'), 'D' => t('Défenseur'), 'M' => t('Milieu'), 'A' => t('Attaquant')];
$roleLabels = ['joueur' => t('Joueur'), 'entraineur' => t('Entraîneur'), 'dirigeant' => t('Dirigeant'), 'personnage' => t('Personnage emblématique')];
foreach ($items as $k => $s):
    $i = $offset + $k;
    $href = url($s['path']);
    if ($kind === 'matchs'):
        $m = $s['m'];
        $hasScore = is_array($m['sh_score']);
        $hs = $hasScore ? $m['sh_score'][0] : '';
        $as = $hasScore ? $m['sh_score'][1] : '';
        $res = $m['result'];
        $tab = is_array($m['pens']) ? $m['pens'][0] . '-' . $m['pens'][1] : null;
        $comp = $m['label'] ?: ($m['competition'] ?? '');
        $lvH = $m['home_level'] ?: ($m['competition'] === 'Championnat' ? ($m['label'] ?? '') : '');
        $lvA = $m['away_level'] ?: ($m['competition'] === 'Championnat' ? ($m['label'] ?? '') : '');
        $noTeams = $m['home'] === '' || $m['away'] === '';
        if ($view === 'list'): ?>
<a class="mrow" href="<?= e($href) ?>" data-item>
  <span class="mrow__date"><?= e(date_num($m['date'])) ?></span>
  <span class="mrow__main"><span class="mrow__comp"><?= e(comp_round($comp, $m['round'])) ?></span><span class="mrow__teams"><?= $noTeams ? e($m['event'] ?: $s['title']) : e($m['home']) . ' – ' . e($m['away']) ?></span></span>
  <span class="mrow__score"><?= $hasScore ? e($hs . '-' . $as) : '' ?><?php if ($tab): ?> <small>(<?= e($tab) ?> <?= e(t('tab')) ?>)</small><?php endif; ?></span>
  <?php if ($res): ?><span class="res res--<?= e($res) ?>"><?= e($res) ?></span><?php else: ?><span></span><?php endif; ?>
</a>
        <?php else: ?>
<a class="mcard" href="<?= e($href) ?>" data-item>
  <span class="mcard__media">
    <?php if ($s['image']): ?><img src="<?= e(img($s['image'], 480)) ?>" srcset="<?= e(srcset($s['image'], [320, 480, 800])) ?>" sizes="(max-width: 600px) 100vw, 300px" alt="" loading="lazy" decoding="async"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?>
    <span class="mcard__comp"><?= e($comp) ?></span>
    <?php if ($res): ?><span class="res res--<?= e($res) ?> mcard__res" title="<?= e(['V' => t('Victoire'), 'N' => t('Match nul'), 'D' => t('Défaite')][$res] ?? '') ?>"><?= e($res) ?></span><?php endif; ?>
  </span>
  <span class="mcard__body">
    <span class="mcard__date"><?= e(date_num($m['date'])) ?><?= $m['round'] && mb_strtolower((string) $m['round']) !== mb_strtolower($comp) ? ' · ' . e($m['round']) : '' ?></span>
    <?php if ($noTeams): ?>
      <span class="mcard__event"><?= e($m['event'] ?: $s['title']) ?></span>
    <?php else: ?>
    <span class="mcard__score">
      <span class="mcard__team<?= $m['sh'] ? ' is-s' : '' ?>"><?= e($m['home']) ?><?php if ($lvH): ?> <small>(<?= e($lvH) ?>)</small><?php endif; ?></span><b><?= e((string) $hs) ?></b>
      <span class="mcard__team<?= !$m['sh'] ? ' is-s' : '' ?>"><?= e($m['away']) ?><?php if ($lvA): ?> <small>(<?= e($lvA) ?>)</small><?php endif; ?></span><b><?= e((string) $as) ?></b>
    </span>
    <?php endif; ?>
    <?php if ($tab): ?><span class="mcard__tab"><?= e(t('TAB')) ?> <?= e($tab) ?></span><?php elseif (!empty($m['extra']) && stripos((string) $m['extra'], 'a.p') !== false): ?><span class="mcard__tab"><?= e(t('a.p.')) ?></span><?php endif; ?>
  </span>
</a>
        <?php endif;
    else:
        if ($kind === 'lions' || $s['type'] === 'personne') {
            $p = $s['p'] ?? [];
            $title = $p['name'] ?? $s['title'];
            $role = $p['roles'][0] ?? 'joueur';
            $tag = $role === 'joueur' ? ($lineLabels[$p['line'] ?? ''] ?? t('Joueur')) : ($roleLabels[$role] ?? '');
            if (!empty($p['trial'])) {
                $tag .= ' · ' . t("À l'essai");
            }
            $years = ($p['arrival'] ?? null) ? $p['arrival'] . (($p['departure'] ?? null) && $p['departure'] !== $p['arrival'] ? ' – ' . $p['departure'] : '') : '';
            $meta = $years ?: (string) ($p['subtitle'] ?? '');
        } else {
            $title = Pages::shortTitle($s);
            $primary = Categories::primaryOf($s['categories']);
            $tag = $primary && $primary !== $catSlug ? t(Categories::label($primary)) : t(Categories::label($catSlug));
            $meta = $s['excerpt'] !== '' ? mb_strimwidth($s['excerpt'], 0, 90, '…') : '';
        }
        if ($view === 'list'): ?>
<a class="trow" href="<?= e($href) ?>" data-item>
  <span class="trow__img"><?php if ($s['image']): ?><img src="<?= e(img($s['image'], 160)) ?>" alt="" loading="lazy" decoding="async"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></span>
  <span class="trow__main"><span class="trow__tag"><?= e($tag) ?></span><span class="trow__title"><?= e($title) ?></span></span>
  <span class="trow__meta"><?= e($kind === 'lions' ? $meta : ($s['date'] ? substr((string) $s['date'], 0, 4) : '')) ?></span>
</a>
        <?php else:
            [$r, $c] = $pattern[$i % count($pattern)];
            $big = $r === 2 && $c === 2;
        ?>
<a class="tile<?= $big ? ' tile--big' : '' ?>" style="grid-row:span <?= $r ?>;grid-column:span <?= $c ?>" href="<?= e($href) ?>" data-item>
  <?php if ($s['image']): ?><img src="<?= e(img($s['image'], $big || $c > 1 ? 800 : 480)) ?>" srcset="<?= e(srcset($s['image'], $big ? [480, 800, 1200] : [320, 480, 800])) ?>" sizes="(max-width: 600px) 100vw, <?= $c > 1 ? '50vw' : '25vw' ?>" alt="" loading="<?= $k < 6 ? 'eager' : 'lazy' ?>" decoding="async"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?>
  <span class="tile__txt">
    <span class="tile__tag"><?= e($tag) ?></span>
    <span class="tile__title"><?= e($title) ?></span>
    <?php if ($meta !== ''): ?><span class="tile__meta"><?= e($meta) ?></span><?php endif; ?>
  </span>
  <?php if (!empty($s['p']['legend'])): ?><span class="tile__badge"><?= e(t('Légende')) ?></span><?php endif; ?>
</a>
        <?php endif;
    endif;
endforeach;
