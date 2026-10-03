<?php
/**
 * Le Fil jaune : la constellation des coéquipiers d'un joueur.
 * Variables : $a (joueur), $mates (joueurs + n), $top (5 premiers + link), $svg (w, c, dots), $players
 */
use App\Core\View;
use App\Front\Fil;

$c = $svg['c'];
?>
<section class="mhead fjhead">
  <div class="wrap mhead__inner" style="padding-bottom:clamp(28px,4vw,48px)">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><a href="<?= e(url('/interactif/')) ?>"><?= e(t('Interactif')) ?></a><span aria-hidden="true">/</span><a href="<?= e(Fil::base()) ?>"><?= e(t('Le Fil jaune')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e($a['name']) ?></span></nav>
    <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('La constellation de')) ?></span>
    <h1 class="mhead__title fjhead__title"><?= e($a['name']) ?></h1>
    <p class="mhead__intro"><?= e(t('{n} coéquipiers dans les compositions du musée ({g} matchs, {y}). Les plus fidèles brillent au centre ; touchez une étoile pour voir sa propre constellation.', ['n' => count($mates), 'g' => $a['games'], 'y' => $a['years']])) ?></p>
    <div class="row" style="gap:12px;flex-wrap:wrap">
      <a class="btn btn--yellow" href="<?= e(url($a['path'])) ?>"><?= e(t('Sa fiche')) ?></a>
      <a class="btn btn--ghost-light" href="#relier"><?= e(t('Le relier à un autre joueur')) ?></a>
    </div>
  </div>
</section>

<div class="wrap fjland">
  <div class="fjstar">
    <figure class="fjstar__fig">
      <svg viewBox="0 0 <?= (int) $svg['w'] ?> <?= (int) $svg['w'] ?>" role="group" aria-labelledby="fj-star-t">
        <title id="fj-star-t"><?= e(t('Les coéquipiers de {a}, du plus fidèle au plus rare', ['a' => $a['name']])) ?></title>
        <circle cx="<?= $c ?>" cy="<?= $c ?>" r="390" class="fjstar__halo"/>
        <?php foreach ($svg['dots'] as $d): ?><line x1="<?= $c ?>" y1="<?= $c ?>" x2="<?= $d['x'] ?>" y2="<?= $d['y'] ?>" class="fjstar__ray<?= $d['top'] ? ' is-top' : '' ?>"/><?php endforeach; ?>
        <?php foreach ($svg['dots'] as $d): $m = $d['m']; ?>
          <a href="<?= e(Fil::starUrl($m['id'])) ?>" class="fjstar__dot<?= $d['top'] ? ' is-top' : '' ?>">
            <title><?= e($m['name'] . ' — ' . ($m['n'] > 1 ? t('{n} matchs ensemble', ['n' => $m['n']]) : t('1 match ensemble'))) ?></title>
            <circle cx="<?= $d['x'] ?>" cy="<?= $d['y'] ?>" r="<?= $d['r'] ?>"/>
            <?php if ($d['label']): ?><text x="<?= $d['x'] ?>" y="<?= round($d['y'] + $d['r'] + 15, 1) ?>" text-anchor="middle"><?= e($m['name']) ?></text><?php endif; ?>
          </a>
        <?php endforeach; ?>
        <circle cx="<?= $c ?>" cy="<?= $c ?>" r="54" class="fjstar__me"/>
        <?php if ($a['image']): ?>
          <clipPath id="fj-me"><circle cx="<?= $c ?>" cy="<?= $c ?>" r="50"/></clipPath>
          <image href="<?= e(img($a['image'], 320)) ?>" x="<?= $c - 50 ?>" y="<?= $c - 50 ?>" width="100" height="100" preserveAspectRatio="xMidYMin slice" clip-path="url(#fj-me)"/>
        <?php else: ?>
          <text x="<?= $c ?>" y="<?= $c + 16 ?>" text-anchor="middle" class="fjstar__ini"><?= e(mb_substr($a['name'], 0, 1)) ?></text>
        <?php endif; ?>
      </svg>
      <figcaption><?= e(t('Taille de l’étoile : nombre de matchs joués ensemble. En jaune : les dix plus fidèles.')) ?></figcaption>
    </figure>
    <div class="fjstar__side">
      <h2 class="h-3"><?= e(t('Ses inséparables')) ?></h2>
      <ol class="fjrec__list">
        <?php foreach ($top as $m): $l = $m['link']; ?>
          <li><a href="<?= e(Fil::starUrl($m['id'])) ?>"><span class="fjrec__n"><?= (int) $m['n'] ?></span><span><b><?= e($m['name']) ?></b><small><?= e(t('matchs ensemble')) ?> · <?= e($l['years'] ?? '') ?></small></span></a></li>
        <?php endforeach; ?>
      </ol>
      <details class="fjall">
        <summary><?= e(t('Tous ses coéquipiers ({n})', ['n' => count($mates)])) ?></summary>
        <ol class="fjall__list">
          <?php foreach ($mates as $m): ?><li><a href="<?= e(Fil::starUrl($m['id'])) ?>"><?= e($m['name']) ?></a> <span class="fjall__n"><?= (int) $m['n'] ?></span></li><?php endforeach; ?>
        </ol>
      </details>
    </div>
  </div>

  <section class="fjsec" id="relier" aria-labelledby="fj-link">
    <h2 class="h-3" id="fj-link"><?= e(t('Relier {a} à un autre joueur', ['a' => $a['name']])) ?></h2>
    <?= View::partial('partials/fil-form', ['players' => $players, 'a' => $a['name'], 'b' => '', 'id' => 'fj3']) ?>
  </section>
</div>
