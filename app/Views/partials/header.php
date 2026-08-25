<?php
$nav = [
    'home'    => ['Accueil', url('/')],
    'network' => ['Le réseau', url('le-reseau')],
    'job'     => ['Le métier', url('le-metier')],
    'news'    => ['Actualités', url('actualites')],
    'contact' => ['Contact', url('contact')],
];
$page = $page ?? '';
?>
<header class="header">
  <div class="container header__inner">
    <a class="brand" href="<?= e(url('/')) ?>" aria-label="Suisse Immo — accueil">
      <?php partial('logo', ['class' => 'brand__logo']); ?>
      <span class="brand__tag">recrutement</span>
    </a>

    <nav class="nav" aria-label="Navigation principale">
      <?php foreach ($nav as $key => [$label, $href]): ?>
        <a href="<?= e($href) ?>"<?= $page === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="header__cta">
      <a class="btn btn--ghost" href="<?= e(url('contact')) ?>">Nous parler</a>
      <a class="btn btn--magnet" href="<?= e(url('candidater')) ?>" data-cta="header">
        Candidater <?= icon('arrow') ?>
      </a>
      <button class="burger" type="button" aria-expanded="false" aria-controls="mobile-nav" aria-label="Ouvrir le menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<div class="mobile-nav" id="mobile-nav">
  <?php foreach ($nav as [$label, $href]): ?>
    <a href="<?= e($href) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <a class="btn btn--lg btn--block" href="<?= e(url('candidater')) ?>" data-cta="mobile-nav">Candidater <?= icon('arrow') ?></a>
</div>
