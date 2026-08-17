<?php
/**
 * Bandeau et panneau de consentement aux cookies.
 *
 * Conforme aux recommandations de la CNIL : refuser est aussi simple
 * qu'accepter (deux boutons de même poids visuel), rien n'est déposé avant
 * un choix explicite, et le choix reste modifiable à tout moment par le lien
 * du pied de page.
 *
 * @var array $site
 * @var array $categories
 */
?>
<div class="cookies" data-cookies hidden>
  <!-- --------------------------------------------------------- bandeau -->
  <div class="cookies__bandeau" data-cookies-bandeau role="dialog"
       aria-modal="false" aria-labelledby="cookies-titre" aria-describedby="cookies-texte">
    <div class="cookies__corps">
      <h2 class="cookies__titre" id="cookies-titre">Votre vie privée</h2>
      <p class="cookies__texte" id="cookies-texte">
        Ce site utilise uniquement les cookies nécessaires à son fonctionnement.
        Avec votre accord, nous mesurerions aussi son audience et pourrions afficher
        des contenus externes (plan d’accès, vidéos). Vous restez libre de refuser :
        le site fonctionne à l’identique.
        <a href="<?= route('mentions-legales') ?>">En savoir plus</a>
      </p>
    </div>
    <div class="cookies__actions">
      <button class="btn btn--or" type="button" data-cookies-tout>Tout accepter</button>
      <button class="btn btn--or" type="button" data-cookies-rien>Tout refuser</button>
      <button class="cookies__reglages" type="button" data-cookies-ouvrir>Personnaliser</button>
    </div>
  </div>

  <!-- --------------------------------------------------------- panneau -->
  <div class="cookies__voile" data-cookies-voile hidden></div>
  <div class="cookies__panneau" data-cookies-panneau hidden role="dialog" aria-modal="true"
       aria-labelledby="cookies-panneau-titre" tabindex="-1">
    <div class="cookies__panneau-tete">
      <h2 id="cookies-panneau-titre">Vos préférences</h2>
      <button class="cookies__fermer" type="button" data-cookies-fermer aria-label="Fermer">×</button>
    </div>

    <div class="cookies__panneau-corps">
      <?php foreach ($categories as $cle => $cat): ?>
        <div class="cookies__categorie">
          <div class="cookies__categorie-tete">
            <h3><?= e($cat['titre']) ?></h3>
            <?php if (!empty($cat['obligatoire'])): ?>
              <span class="cookies__toujours">Toujours actifs</span>
            <?php else: ?>
              <label class="cookies__bascule">
                <input type="checkbox" data-cookies-categorie="<?= e($cle) ?>">
                <span class="cookies__curseur" aria-hidden="true"></span>
                <span class="cookies__bascule-nom">Autoriser <?= e(mb_strtolower($cat['titre'])) ?></span>
              </label>
            <?php endif; ?>
          </div>
          <p><?= e($cat['texte']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="cookies__panneau-pied">
      <button class="btn btn--contour" type="button" data-cookies-rien>Tout refuser</button>
      <button class="btn btn--contour" type="button" data-cookies-tout>Tout accepter</button>
      <button class="btn btn--or" type="button" data-cookies-enregistrer>Enregistrer mes choix</button>
    </div>
  </div>
</div>
