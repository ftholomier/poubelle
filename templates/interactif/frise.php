<?php
/** Frise 1928 → aujourd'hui (maquette « Frise »). Variables : $events */
$decades = range(1920, (int) (floor((int) date('Y') / 10) * 10), 10);
?>
<section class="wrap frhead">
  <div class="stack" style="gap:12px;flex:1 1 480px;min-width:0">
    <span class="eyebrow eyebrow--lg"><?= e(t('La frise')) ?></span>
    <h1 class="h-xl frhead__title">1928 → <span class="blue"><?= e(t("aujourd'hui")) ?></span></h1>
    <p class="muted" style="font-size:20px;margin:0"><?= e(t('Faites glisser la frise, ou utilisez les flèches.')) ?></p>
  </div>
  <div class="row" style="gap:10px">
    <button type="button" class="frbtn" data-fr="-1" aria-label="<?= e(t('Précédent')) ?>">←</button>
    <button type="button" class="frbtn frbtn--on" data-fr="1" aria-label="<?= e(t('Suivant')) ?>">→</button>
  </div>
</section>
<div class="wrap">
  <div class="frdecades">
    <?php foreach ($decades as $d): ?>
      <button type="button" data-frdec="<?= $d ?>"><span></span><b><?= $d < 2000 ? "'" . substr((string) $d, 2) : $d ?></b></button>
    <?php endforeach; ?>
  </div>
</div>
<div class="frtrack" data-frtrack tabindex="0" aria-label="<?= e(t('Frise chronologique')) ?>">
  <div class="frtrack__inner">
    <span class="frtrack__line" aria-hidden="true"></span>
    <?php foreach ($events as $i => $ev): $top = $i % 2 === 0; $tone = $ev['tone'] ?? ''; ?>
    <div class="frev<?= $top ? ' frev--top' : ' frev--bottom' ?>" id="<?= (int) $ev['year'] ?>" data-year="<?= (int) $ev['year'] ?>">
      <span class="frev__dot<?= $tone === 'y' ? ' is-y' : '' ?>"></span>
      <span class="frev__stem"></span>
      <<?= !empty($ev['href']) ? 'a href="' . e(url($ev['href'])) . '"' : 'div' ?> class="frev__card frev__card--<?= e($tone ?: 'p') ?>">
        <span class="frev__img"><?php if (!empty($ev['image'])): ?><img src="<?= e(img($ev['image'], 480)) ?>" alt="" loading="lazy" draggable="false"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></span>
        <span class="frev__txt"><b><?= (int) $ev['year'] ?></b><span class="frev__t"><?= e($ev['title']) ?></span><?php if (!empty($ev['text'])): ?><small><?= e($ev['text']) ?></small><?php endif; ?></span>
      </<?= !empty($ev['href']) ? 'a' : 'div' ?>>
    </div>
    <?php endforeach; ?>
  </div>
</div>
