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
      <svg class="brand__mark" viewBox="0 0 48 48" fill="none" aria-hidden="true">
        <rect width="48" height="48" rx="13" fill="url(#bg)"/>
        <path d="M14 30V20.5L24 13l10 7.5V30" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M19.5 35v-9h9v9" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
        <defs><linearGradient id="bg" x1="0" y1="0" x2="48" y2="48"><stop stop-color="#E62F43"/><stop offset="1" stop-color="#FF8A3D"/></linearGradient></defs>
      </svg>
      <span class="brand__text">
        <span class="brand__name">Suisse Immo</span>
        <span class="brand__sub">Recrutement</span>
      </span>
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
