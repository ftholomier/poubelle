<?php
/** Page introuvable. */
use App\Vitrine\Host;
?>
<section class="vnotfound">
  <div class="wrap vnotfound__in">
    <span class="vnotfound__n" aria-hidden="true">404</span>
    <h1 class="h-section">Hors-jeu !</h1>
    <p class="lead">Cette page n’existe pas, ou plus. Elle est peut-être au musée en ligne, qui rassemble toute l’histoire du club.</p>
    <div class="row gap-14">
      <a class="btn btn--navy" href="<?= e(Host::url('/')) ?>">Accueil de l’association</a>
      <a class="btn btn--yellow" href="<?= e(Host::museum('/')) ?>">Le musée en ligne ↗</a>
    </div>
  </div>
</section>
