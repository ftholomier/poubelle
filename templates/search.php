<?php
/**
 * Page de résultats de recherche.
 * Variables : $q, $r (résultat de Search::query), $type, $pageNum, $pages, $base, $qs, $shortcuts
 */

use App\Services\Search;

$types = ['personne' => t('Nos Lions'), 'match' => t('Matchs'), 'article' => t('Articles'), 'page' => t('Pages'), 'objet' => t('Objets'), 'moment' => t('Moments')];
$all = array_sum($r['counts']);
?>
<section class="mhead">
  <div class="wrap mhead__inner" style="padding-bottom:clamp(28px,4vw,48px)">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t('Recherche')) ?></span></nav>
    <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Rechercher dans le musée')) ?></span>
    <form class="searchbig" action="<?= e($base) ?>" method="get" role="search">
      <label class="sr-only" for="qsearch"><?= e(t('Recherche')) ?></label>
      <input id="qsearch" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Un joueur, un match, une saison…')) ?>"<?= $q === '' ? ' autofocus' : '' ?>>
      <button class="btn btn--yellow" type="submit"><?= e(t('Rechercher')) ?></button>
    </form>
    <?php if ($q !== ''): ?>
      <p class="mhead__intro"><?= $all ? e(t('{n} fiches correspondent à « {q} ».', ['n' => $all, 'q' => $q])) : e(t('Aucune fiche ne correspond à « {q} ».', ['q' => $q])) ?></p>
    <?php endif; ?>
  </div>
</section>

<div class="wrap mbody">
  <?php if ($q !== '' && $all): ?>
  <div class="mtools__chips">
    <a class="mchip<?= !$type ? ' is-on' : '' ?>" href="<?= e($base . $qs(['type' => null])) ?>"><?= e(t('Tout')) ?> <small><?= (int) $all ?></small></a>
    <?php foreach ($types as $k => $label): if (empty($r['counts'][$k])) continue; ?>
      <a class="mchip<?= $type === $k ? ' is-on' : '' ?>" href="<?= e($base . $qs(['type' => $k])) ?>"><?= e($label) ?> <small><?= (int) $r['counts'][$k] ?></small></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($shortcuts): ?>
  <div class="mextras__list">
    <?php foreach ($shortcuts as $sc): ?>
      <a class="mextra" href="<?= e($sc['href']) ?>"><span class="mextra__t"><?= e($sc['label']) ?></span><small class="muted"><?= e($sc['meta']) ?></small></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($q === ''): ?>
    <div class="mempty">
      <span class="mempty__t"><?= e(t('Que cherchez-vous ?')) ?></span>
      <span><?= e(t('Essayez « Bonal », « 2007 », « Coupe de France 1988 » ou le nom d’un joueur.')) ?></span>
    </div>
  <?php elseif (!$r['items']): ?>
    <div class="mempty">
      <span class="mempty__t"><?= e(t('Rien dans les réserves…')) ?></span>
      <span><?= e(t('Vérifiez l’orthographe, essayez un seul mot, ou posez la question à notre assistant.')) ?></span>
      <a class="btn btn--yellow btn--sm" href="<?= e(url('/contribuer/')) ?>"><?= e(t('Proposer une archive')) ?></a>
    </div>
  <?php else: ?>
    <ol class="sresults" start="<?= ($pageNum - 1) * 20 + 1 ?>">
      <?php foreach ($r['items'] as $s): $d = Search::describe($s); ?>
        <li>
          <a class="sres" href="<?= e(url($s['path'])) ?>">
            <span class="sres__img"><?php if ($s['image']): ?><img src="<?= e(img($s['image'], 160)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></span>
            <span class="sres__body">
              <span class="sres__type"><?= e($d['type']) ?><?= $d['meta'] !== '' ? ' · ' . e($d['meta']) : '' ?></span>
              <span class="sres__title"><?= e($d['label']) ?></span>
              <span class="sres__snip"><?= Search::snippet($s, $r['tokens']) ?></span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php if ($pages > 1): ?>
    <nav class="mpager" aria-label="<?= e(t('Pagination')) ?>">
      <?php if ($pageNum > 1): ?><a class="btn btn--ghost btn--sm" style="display:inline-flex" href="<?= e($base . $qs(['page' => $pageNum > 2 ? (string) ($pageNum - 1) : null])) ?>">← <?= e(t('Précédents')) ?></a><?php endif; ?>
      <span class="mpager__info" style="display:inline"><?= e(t('Page {p} sur {n}', ['p' => $pageNum, 'n' => $pages])) ?></span>
      <?php if ($pageNum < $pages): ?><a class="btn btn--shadow btn--sm" href="<?= e($base . $qs(['page' => (string) ($pageNum + 1)])) ?>"><?= e(t('Suivants')) ?> →</a><?php endif; ?>
    </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
