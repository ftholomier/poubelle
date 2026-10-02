<?php
/** Accueil de la rubrique INTERACTIF. Variables : $groups */
?>
<section class="mhead">
  <div class="wrap mhead__inner" style="padding-bottom:clamp(32px,4vw,56px)">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t('Interactif')) ?></span></nav>
    <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Jouer, explorer, participer')) ?></span>
    <h1 class="mhead__title"><?= e(t('Interactif')) ?></h1>
    <p class="mhead__intro"><?= e(t('Le musée se visite aussi en jouant : cartes à collectionner, quiz, carte du monde des Lions, frise, vote du centenaire…')) ?></p>
  </div>
</section>
<div class="wrap ilanding">
  <?php foreach ($groups as $g): ?>
  <section class="ilanding__group">
    <h2 class="h-3"><?= e($g['title']) ?></h2>
    <div class="ilanding__grid">
      <?php foreach ($g['tools'] as $tool): ?>
        <a class="itool" href="<?= e($tool['href']) ?>" data-reveal>
          <span class="itool__icon" aria-hidden="true"><?= e($tool['icon']) ?></span>
          <span class="itool__label"><?= e($tool['label']) ?></span>
          <span class="itool__d"><?= e($tool['d']) ?></span>
          <span class="itool__go" aria-hidden="true">→</span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>
</div>
