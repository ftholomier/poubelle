<?php
/**
 * Le Lion illustré : une édition de journal tirée au hasard (Une, deux articles, « En images »,
 * brèves). Titres = fiches du musée, textes = légendes de la médiathèque : rien d'inventé.
 * Variables : $lead, $side, $row, $briefs, $number, $special
 */
use App\Front\Walls;

$kicker = function (array $p): string {
    $k = match ($p['type']) {
        'match' => t('Match'), 'personne' => t('Portrait'), 'article' => t('Archives'), 'objet' => t('Réserves'),
        'moment' => t('Centenaire'), default => t('Musée'),
    };
    if ($p['type'] === 'match' && $p['date']) {
        return $k . ' · ' . date_fr((string) $p['date']);
    }
    return $p['year'] ? $k . ' · ' . $p['year'] : $k;
};
$head = fn (array $p): string => $p['title'] !== '' ? $p['title'] : $p['caption'];
$i = 0;
?>
<article class="lj">
  <header class="lj__mast">
    <p class="lj__top"><span><?= e(t('Journal photographique du musée Sochaux Rétro')) ?></span><span><?= e(t('Une édition unique, tirée pour vous')) ?></span></p>
    <p class="lj__title" aria-hidden="true"><?= e(t('Le Lion illustré')) ?></p>
    <p class="lj__line"><span><?= e(ucfirst(date_fr(date('Y-m-d'), true))) ?></span><span><?= e(t('N°')) ?> <?= number_format((int) $number, 0, ',', ' ') ?></span><?php if ($special !== ''): ?><span class="lj__special"><?= e(t('Édition spéciale')) ?> : <?= e($special) ?></span><?php endif; ?><span><?= e(t('Gratuit')) ?></span></p>
  </header>
  <?php if (!$lead): ?><p class="wempty"><?= e(t('Aucune photo pour ce choix : essayez une autre décennie ou un autre photographe.')) ?></p><?php else: ?>
  <div class="lj__front">
    <article class="lj__lead">
      <span class="lj__kicker"><?= e($kicker($lead)) ?></span>
      <h2 class="lj__h"><?= e($head($lead)) ?></h2>
      <figure class="lj__fig">
        <button type="button" class="lj__shot" <?= Walls::attrs($lead, $i++) ?>><img src="<?= e($lead['src1200']) ?>" alt="<?= e($lead['alt']) ?>" width="1200" height="800" decoding="async"></button>
        <figcaption><?php if ($lead['caption'] !== '' && $lead['caption'] !== $head($lead)): ?><span class="lj__cap"><?= e($lead['caption']) ?></span><?php endif; ?><span class="lj__credit"><?= e(t('Photo')) ?> : <?= e($lead['credit']) ?></span></figcaption>
      </figure>
      <?php if ($lead['href']): ?><a class="lj__more" href="<?= e($lead['href']) ?>"><?= e(t('Lire la fiche')) ?> →</a><?php endif; ?>
    </article>
    <div class="lj__side">
      <?php foreach ($side as $p): ?>
        <article class="lj__item">
          <span class="lj__kicker"><?= e($kicker($p)) ?></span>
          <h3 class="lj__h lj__h--sm"><?= e($head($p)) ?></h3>
          <figure class="lj__fig">
            <button type="button" class="lj__shot" <?= Walls::attrs($p, $i++) ?>><img src="<?= e($p['src800']) ?>" alt="<?= e($p['alt']) ?>" width="800" height="533" loading="lazy" decoding="async"></button>
            <figcaption><?php if ($p['caption'] !== '' && $p['caption'] !== $head($p)): ?><span class="lj__cap"><?= e($p['caption']) ?></span><?php endif; ?><span class="lj__credit"><?= e(t('Photo')) ?> : <?= e($p['credit']) ?></span></figcaption>
          </figure>
          <?php if ($p['href']): ?><a class="lj__more" href="<?= e($p['href']) ?>"><?= e(t('Lire la fiche')) ?> →</a><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if ($row): ?>
  <section class="lj__band">
    <h2 class="lj__rubric"><?= e(t('En images')) ?></h2>
    <div class="lj__grid lj__grid--4">
      <?php foreach ($row as $p): ?>
        <figure class="lj__fig lj__fig--sm">
          <button type="button" class="lj__shot" <?= Walls::attrs($p, $i++) ?>><img src="<?= e($p['src480']) ?>" alt="<?= e($p['alt']) ?>" width="480" height="320" loading="lazy" decoding="async"></button>
          <figcaption><span class="lj__cap"><?= e(mb_strimwidth($p['caption'] !== '' ? $p['caption'] : $p['title'], 0, 140, '…')) ?></span><span class="lj__credit"><?= e(t('Photo')) ?> : <?= e($p['credit']) ?></span></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
  <?php if ($briefs): ?>
  <section class="lj__band">
    <h2 class="lj__rubric"><?= e(t('Brèves')) ?></h2>
    <div class="lj__grid lj__grid--6">
      <?php foreach ($briefs as $p): ?>
        <figure class="lj__fig lj__fig--xs">
          <button type="button" class="lj__shot" <?= Walls::attrs($p, $i++) ?>><img src="<?= e($p['src480']) ?>" alt="<?= e($p['alt']) ?>" width="480" height="480" loading="lazy" decoding="async"></button>
          <figcaption><?php if ($p['href']): ?><a href="<?= e($p['href']) ?>"><?= e(mb_strimwidth($p['title'] !== '' ? $p['title'] : $p['caption'], 0, 70, '…')) ?></a><?php endif; ?><span class="lj__credit"><?= e($p['credit']) ?></span></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
  <?php endif; ?>
  <p class="lj__foot"><?= e(t('Toutes les photos de cette édition sont créditées et viennent de la médiathèque du musée.')) ?></p>
</article>
