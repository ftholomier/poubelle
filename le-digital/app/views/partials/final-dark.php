<?php /** Appel final sur fond nuit. @var string $title @var string|null $text @var string $cta @var string $variant (acc|sol|avis|blog) */
$variant ??= 'acc'; ?>
<section class="final-wrap final-wrap--<?= e($variant) ?>">
  <div class="final final--dark final--<?= e($variant) ?>" data-reveal="scale">
    <h2 class="final__mid"><?= t($title) ?></h2>
    <?php if (!empty($text)): ?><p class="final__p3"><?= t($text) ?></p><?php endif; ?>
    <a class="btn btn-red btn-xl btn-red-lt" href="<?= e($href ?? '/on-prend-rdv') ?>"><?= t($cta) ?></a>
  </div>
</section>
