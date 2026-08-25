<div class="sticky-cta" role="complementary" aria-label="Raccourci de candidature">
  <span class="sticky-cta__text">
    <b>Votre secteur est peut-être libre.</b>
    <small>Candidature en 2 minutes · réponse sous <?= e(settings('funnel.response_delay', '48 h')) ?></small>
  </span>
  <a class="btn btn--magnet" href="<?= e(url('candidater')) ?>" data-cta="sticky"><?= e(settings('funnel.sticky_cta_label', 'Candidater')) ?> <?= icon('arrow') ?></a>
  <button class="sticky-cta__close" type="button" aria-label="Masquer"><?= icon('close') ?></button>
</div>
