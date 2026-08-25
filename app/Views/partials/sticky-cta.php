<?php
// Sur la page d'accueil le simulateur est déjà là : ancre simple.
// Ailleurs, on renvoie vers l'accueil, section simulateur.
$simHref = ($page ?? '') === 'home' ? '#simulateur' : url('/') . '#simulateur';
?>
<div class="sticky-cta" role="complementary" aria-label="Raccourcis">
  <span class="sticky-cta__text">
    <b>Votre secteur est peut-être libre.</b>
    <small>Candidature en 2 minutes · réponse sous <?= e(settings('funnel.response_delay', '48 h')) ?></small>
  </span>
  <span class="sticky-cta__actions">
    <a class="sticky-cta__sim" href="<?= e($simHref) ?>" data-cta="sticky-simulateur">
      <?= icon('calc') ?> <span>Combien puis-je gagner&nbsp;?</span>
    </a>
    <a class="btn btn--magnet" href="<?= e(url('candidater')) ?>" data-cta="sticky"><?= e(settings('funnel.sticky_cta_label', 'Candidater')) ?> <?= icon('arrow') ?></a>
  </span>
  <button class="sticky-cta__close" type="button" aria-label="Masquer"><?= icon('close') ?></button>
</div>
