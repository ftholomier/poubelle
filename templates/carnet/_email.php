<?php
/** Formulaire e-mail (création du carnet ou lien perdu). Variables : $mode (creer|renvoyer), $mid (match à ajouter, 0 sinon) */
?>
<form class="cnmail" data-cn-mail data-mode="<?= e($mode) ?>" data-id="<?= (int) $mid ?>" novalidate>
  <label class="cnmail__l" for="cn-email-<?= e($mode) ?>"><?= e($mode === 'creer' ? t('Votre e-mail, pour recevoir le lien personnel de votre carnet') : t('L’e-mail de votre carnet')) ?></label>
  <div class="cnmail__row">
    <input id="cn-email-<?= e($mode) ?>" type="email" name="email" required autocomplete="email" placeholder="<?= e(t('vous@exemple.fr')) ?>">
    <input type="text" name="website" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
    <button class="btn btn--yellow" type="submit"><?= e($mode === 'creer' ? t('Créer mon carnet') : t('Recevoir le lien')) ?></button>
  </div>
  <p class="cnmail__note"><?= e(t('Pas de mot de passe : le lien reçu par e-mail ouvre votre carnet sur n’importe quel appareil. Votre adresse sert seulement à cela, jamais à de la publicité ; vous pouvez supprimer votre carnet à tout moment.')) ?></p>
  <p class="cnmail__msg" data-cn-msg role="status"></p>
</form>
