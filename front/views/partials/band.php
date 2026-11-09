<?php
/** Bandeau de titre gris des pages internes. @var string $eyebrow @var string $h1 @var string $lead @var string $side @var bool $small */
?>
<div class="band<?= !empty($small) ? ' band--sm' : '' ?>">
  <div class="band__in<?= !empty($side) ? ' band__in--flex' : '' ?>">
    <div>
      <?php if (!empty($eyebrow)): ?><div class="band__e"><?= e($eyebrow) ?></div><?php endif; ?>
      <h1><?= e($h1 ?? '') ?></h1>
      <?php if (!empty($lead)): ?><p class="band__p"><?= e($lead) ?></p><?php endif; ?>
    </div>
    <?= $side ?? '' ?>
  </div>
</div>
