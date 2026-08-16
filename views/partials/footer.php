<?php /** @var array $site */ ?>
<footer class="site-pied">
  <p><?= e($site['nom']) ?> — <?= e($site['adresse']['rue']) ?>,
     <?= e($site['adresse']['cp']) ?> <?= e($site['adresse']['ville']) ?></p>
  <p><a href="tel:<?= e(preg_replace('/\s+/', '', $site['contact']['telephone'])) ?>"><?= e($site['contact']['telephone']) ?></a></p>
</footer>
