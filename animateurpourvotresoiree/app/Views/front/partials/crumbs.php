<?php /** @var array<int,array{0:string,1:string}> $crumbs */ ?>
<nav aria-label="Fil d'Ariane">
  <ol class="crumbs">
    <?php foreach ($crumbs as $i => [$name, $url]): ?>
      <li><?php if ($i < count($crumbs) - 1): ?><a href="<?= e($url) ?>"><?= e($name) ?></a><?php else: ?><span aria-current="page"><?= e($name) ?></span><?php endif; ?></li>
    <?php endforeach; ?>
  </ol>
</nav>
