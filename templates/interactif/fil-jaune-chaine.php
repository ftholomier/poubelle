<?php
/**
 * Le Fil jaune : la chaîne de a à b. Variables : $a, $b, $steps (p, link), $n (passes, null si pas reliés), $players
 */
use App\Core\View;
use App\Front\Fil;

$lines = ['G' => t('Gardien'), 'D' => t('Défenseur'), 'M' => t('Milieu'), 'A' => t('Attaquant')];
$share = $n === null ? '' : t('{a} → {b} : {n} passe(s) sur le Fil jaune du musée Sochaux Rétro', ['a' => $a['name'], 'b' => $b['name'], 'n' => $n]);
?>
<section class="mhead fjhead">
  <div class="wrap mhead__inner" style="padding-bottom:clamp(28px,4vw,48px)">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><a href="<?= e(url('/interactif/')) ?>"><?= e(t('Interactif')) ?></a><span aria-hidden="true">/</span><a href="<?= e(Fil::base()) ?>"><?= e(t('Le Fil jaune')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e($a['name'] . ' – ' . $b['name']) ?></span></nav>
    <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Le Fil jaune')) ?></span>
    <h1 class="mhead__title fjhead__title"><?= e($a['name']) ?> <span class="yellow" aria-hidden="true">→</span><span class="sr-only"><?= e(t('à')) ?></span> <?= e($b['name']) ?></h1>
    <?php if ($n === null): ?>
      <p class="mhead__intro"><?= e(t('Ces deux joueurs ne sont pas encore reliés par les compositions du musée : il manque des feuilles de match entre leurs générations.')) ?></p>
    <?php elseif ($n === 0): ?>
      <p class="mhead__intro"><?= e(t('C’est le même joueur ! Choisissez-en un autre ci-dessous.')) ?></p>
    <?php else: ?>
      <p class="fjhead__n"><b><?= (int) $n ?></b> <?= e($n > 1 ? t('passes') : t('passe')) ?></p>
      <p class="mhead__intro"><?= e($n === 1 ? t('Ils ont joué ensemble.') : t('{n} coéquipiers les relient, de match en match.', ['n' => $n - 1])) ?></p>
    <?php endif; ?>
  </div>
</section>

<div class="wrap fjland">
  <?php if ($n): ?>
  <ol class="fjchain" aria-label="<?= e(t('La chaîne, du premier au second joueur')) ?>">
    <?php foreach ($steps as $i => $st): $p = $st['p']; $l = $st['link']; ?>
      <?php if ($l): ?>
      <li class="fjchain__link">
        <span class="fjchain__n"><b><?= (int) $l['n'] ?></b> <?= e($l['n'] > 1 ? t('matchs ensemble') : t('match ensemble')) ?> <span class="fjchain__y">(<?= e($l['years']) ?>)</span></span>
        <?php if ($l['first']): ?><a class="fjchain__m" href="<?= e($l['first']['href']) ?>"><?= e($l['n'] > 1 ? t('Le premier') : t('Le match')) ?> : <?= e($l['first']['label']) ?>, <?= e($l['first']['comp']) ?>, <?= e($l['first']['date']) ?> →</a><?php endif; ?>
      </li>
      <?php endif; ?>
      <li class="fjchain__p<?= $i === 0 || $i === count($steps) - 1 ? ' is-end' : '' ?>">
        <a class="fjcard" href="<?= e(url($p['path'])) ?>">
          <span class="fjcard__img"><?php if ($p['image']): ?><img src="<?= e(img($p['image'], 320)) ?>" alt="" loading="lazy"><?php else: ?><span class="fjface__ph" aria-hidden="true"><?= e(mb_substr($p['name'], 0, 1)) ?></span><?php endif; ?></span>
          <span class="fjcard__t"><b><?= e($p['name']) ?></b><small><?= e(trim(($lines[$p['pos'] ?? ''] ?? '') . ' · ' . $p['years'], ' ·')) ?></small><small><?= e(tn((int) $p['games'], '{n} match dans les compositions', '{n} matchs dans les compositions')) ?></small></span>
        </a>
        <a class="fjchain__star" href="<?= e(Fil::starUrl($p['id'])) ?>"><?= e(t('Sa constellation')) ?></a>
      </li>
    <?php endforeach; ?>
  </ol>
  <div class="row" style="gap:12px;flex-wrap:wrap">
    <a class="btn btn--navy" href="<?= e(Fil::chainUrl($b['id'], $a['id'])) ?>"><?= e(t('Dans l’autre sens')) ?></a>
    <button type="button" class="btn btn--ghost" data-share data-share-title="<?= e(t('Le Fil jaune') . ' : ' . $a['name'] . ' → ' . $b['name']) ?>" data-share-text="<?= e($share) ?>"><?= e(t('Partager')) ?></button>
  </div>
  <?php elseif ($n === null): ?>
    <p class="fjnote fjnote--box"><?= e(t('Vous avez des compositions de match de ces années-là ? Le musée les recherche.')) ?> <a href="<?= e(url('/contribuer/')) ?>"><?= e(t('Contribuer')) ?> →</a></p>
  <?php endif; ?>

  <section class="fjsec" aria-labelledby="fj-again">
    <h2 class="h-3" id="fj-again"><?= e(t('Relier deux autres joueurs')) ?></h2>
    <?= View::partial('partials/fil-form', ['players' => $players, 'a' => $a['name'], 'b' => '', 'id' => 'fj2']) ?>
  </section>
</div>
