<?php
/**
 * Panneau de diagnostic publicitaire, affiché sur ?pub=diag.
 *
 * Le serveur ne peut pas savoir ce qui se passe dans le navigateur du
 * visiteur : consentement mémorisé, script bloqué par une extension, réponse
 * « unfilled » de Google. Ce panneau rend cette chaîne lisible là où elle
 * s'exécute, sans rien exposer qui ne soit déjà dans le code source de la page.
 */
use App\Services\Ads;
?>
<div class="ad-diag" data-ad-diag hidden>
  <h4>Diagnostic publicitaire</h4>
  <dl data-ad-diag-list></dl>
  <p class="ad-diag-actions">
    <button type="button" class="btn btn-ghost btn-sm" data-ad-diag-reset>Réinitialiser le consentement</button>
    <button type="button" class="btn btn-ghost btn-sm" data-ad-diag-close>Fermer</button>
  </p>
  <p class="ad-diag-note">
    Serveur : mode <strong><?= e(Ads::mode()) ?></strong>,
    éditeur <strong><?= e(Ads::client() !== '' ? Ads::client() : 'non configuré') ?></strong>.
    Ajoutez <code>?pub=diag</code> à n’importe quelle page pour revoir ce panneau.
  </p>
</div>
