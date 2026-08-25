<?php $cta = (array) content('final_cta'); ?>
<section class="section" id="candidater">
  <div class="container">
    <div class="cta-final" data-reveal="scale">
      <span class="eyebrow"><?= e($cta['eyebrow'] ?? '') ?></span>
      <h2 class="h1" style="margin:18px 0 16px;max-width:16ch;margin-inline:auto"><?= e($cta['title'] ?? '') ?></h2>
      <p class="lead"><?= e($cta['lead'] ?? '') ?></p>
      <div class="cluster" style="margin-top:32px">
        <a class="btn btn--light btn--lg btn--magnet" href="<?= e(url('candidater')) ?>" data-cta="final">
          <?= e($cta['cta'] ?? 'Candidater') ?> <?= icon('arrow') ?>
        </a>
        <a class="btn btn--ghost btn--lg" style="border-color:rgba(255,255,255,.45);color:#fff" href="<?= e(url('contact')) ?>">
          <?= e($cta['secondary'] ?? 'Nous contacter') ?>
        </a>
      </div>
      <div class="cta-reassure">
        <?php foreach ((array) ($cta['reassurance'] ?? []) as $r): ?>
          <span><?= icon('check-circle') ?> <?= e($r) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
