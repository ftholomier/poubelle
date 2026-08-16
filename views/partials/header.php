<?php /** @var array $site */ ?>
<header class="site-tete">
  <a class="site-tete__logo" href="<?= url('/') ?>">
    <img src="<?= asset('assets/img/logo/logo-etang-fourchu.svg') ?>"
         alt="<?= e($site['nom']) ?> — <?= e($site['baseline']) ?>" width="180">
  </a>
  <nav class="site-nav" aria-label="Navigation principale">
    <ul>
      <?php foreach ($site['menu'] ?? [] as $lien): ?>
        <li><a href="<?= url($lien['url']) ?>"><?= e($lien['libelle']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
</header>
