<?php /** @var array|null $page @var array $hebergements @var array $etangs */ ?>
<section class="hero">
  <h1><?= e($page['titre'] ?? 'Étang Fourchu') ?></h1>
  <p><?= e($page['accroche'] ?? '') ?></p>
</section>
