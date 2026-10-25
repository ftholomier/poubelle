<?php /** Appel final en dégradé rouge. @var string $title @var string|null $text @var string $cta */ ?>
<section class="final-wrap">
  <div class="final final--grad" data-reveal="scale">
    <h2 class="final__big"><?= t($title) ?></h2>
    <?php if (!empty($text)): ?><p class="final__p2"><?= t($text) ?></p><?php endif; ?>
    <a class="btn btn-white btn-xl btn-hover-ink" href="<?= e($href ?? '/on-prend-rdv') ?>"><?= t($cta) ?></a>
  </div>
</section>
