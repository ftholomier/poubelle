<?php
/** @var ?array $pro */
$path = App\Core\Request::path();
$isHome = $path === '/';
?>
<header class="site-header" id="top">
  <div class="wrap">
    <a href="/" class="logo" aria-label="Animateur pour votre soirée — accueil">
      <span class="logo-mark" aria-hidden="true">A</span>
      <span class="logo-word">animateur<em>pour</em>votresoirée</span>
    </a>
    <button type="button" class="burger" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="main-nav" data-burger><span></span></button>
    <nav class="nav" id="main-nav" aria-label="Navigation principale">
      <a href="/recherche/"<?= str_starts_with($path, '/recherche') || str_starts_with($path, '/carte') ? ' class="active" aria-current="page"' : '' ?>>Trouver un pro</a>
      <a href="<?= $isHome ? '#events' : '/#events' ?>" class="nav-secondary">Événements</a>
      <a href="<?= $isHome ? '#how' : '/#how' ?>" class="nav-secondary">Comment ça marche</a>
      <a href="/devis/" class="btn-pill btn-pill-coral<?= str_starts_with($path, '/devis') ? ' active' : '' ?>"<?= str_starts_with($path, '/devis') ? ' aria-current="page"' : '' ?> title="Gratuit : votre demande est envoyée aux pros de votre secteur, qui vous répondent">Déposer une demande</a>
      <a href="/favoris/" class="nav-icon" title="Mes favoris" aria-label="Mes favoris"><?= icon('heart', 20) ?><span class="count hidden" data-fav-count>0</span><span class="sr-only">Favoris</span></a>
      <?php if ($pro): ?>
        <a href="/espace-pro/" class="btn-pill"><?= icon('user', 16) ?> Mon espace</a>
      <?php else: ?>
        <a href="/professionnels/" class="btn-pill">Je suis un pro →</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
