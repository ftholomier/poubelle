<?php
/** Page simple (404, annulation de rendez-vous…). @var string $title @var string $text @var array|null $action @var string|null $extra */
?>
<section class="hero-c hero-c--simple">
  <div class="dots dots--top" aria-hidden="true"></div>
  <div class="hero-c__in">
    <?php if (!empty($tag)): ?><span class="pill-r"><?= t($tag) ?></span><?php endif; ?>
    <h1 class="hero-c__title hero-c__title--simple"><?= t($title) ?></h1>
    <p class="hero-c__p"><?= t($text) ?></p>
    <?= $extra ?? '' ?>
    <?php if (!empty($action)): ?><a class="btn btn-red btn-xl" href="<?= e($action['href']) ?>"><?= t($action['label']) ?></a><?php endif; ?>
  </div>
</section>
