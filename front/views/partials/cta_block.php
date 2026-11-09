<?php
/** @var array $cta  title, text, button */
$ui = Content::ui();
$cta = $cta ?? (Content::page('home')['cta'] ?? []);
?>
<section class="ctab">
  <div class="ctab__box" data-reveal="zoom">
    <div class="ctab__skew"></div>
    <div class="ctab__g">
      <div>
        <h2><?= e($cta['title'] ?? '') ?></h2>
        <?php if (!empty($cta['text'])): ?><p><?= e($cta['text']) ?></p><?php endif; ?>
      </div>
      <div class="ctab__btns">
        <?= View::btn($cta['button'] ?? 'Demander un devis', Content::path('devis'), 'btn-y btn-lg') ?>
        <?= View::btn($ui['cta_bar']['contact'] ?? 'Contactez-nous', Content::path('contact'), 'btn-ow') ?>
      </div>
    </div>
  </div>
</section>
