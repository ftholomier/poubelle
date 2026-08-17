<?php
/**
 * Contact : coordonnées et formulaire.
 *
 * @var array $page
 * @var array $erreurs
 * @var array $valeurs
 * @var App\Core\View $view
 * @var App\Core\Content $content
 */
$site = $content->load('site');
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<section class="section section--noire">
  <div class="conteneur">
    <div class="coordonnees">
      <div class="coordonnee reveler">
        <h3>Téléphone</h3>
        <p><a href="tel:+33<?= e(substr(preg_replace('/\D+/', '', $page['telephone']), 1)) ?>"><?= e($page['telephone']) ?></a></p>
      </div>
      <div class="coordonnee reveler" data-delai="1">
        <h3>Adresse</h3>
        <p><?= e($site['adresse']['rue']) ?></p>
        <small><?= e($site['adresse']['cp']) ?> <?= e($site['adresse']['ville']) ?>, <?= e($site['adresse']['pays']) ?></small>
      </div>
      <div class="coordonnee reveler" data-delai="2">
        <h3>Réservation</h3>
        <p style="font-size:1.05rem;margin-top:.4rem;">
          <a class="lien-fleche" href="<?= e($site['reservation']['hebergement']['url']) ?>" target="_blank" rel="noopener">Hébergement</a><br>
          <a class="lien-fleche" href="<?= e($site['reservation']['etang']['url']) ?>" target="_blank" rel="noopener" style="margin-top:.6rem;">Étang</a>
        </p>
      </div>
    </div>
  </div>
</section>

<section class="section section--noire-2">
  <div class="conteneur conteneur--etroit">
    <div class="centre">
      <p class="surtitre surtitre--centre reveler">Écrivez-nous</p>
      <h2 class="titre-section reveler"><?= e($page['formulaire']['titre']) ?></h2>
      <hr class="filet-or filet-or--centre">
    </div>

    <form class="formulaire reveler" method="post" action="<?= route('contact') ?>" novalidate>
      <?php if (!empty($erreurs['envoi'])): ?>
        <p class="form__alerte" role="alert"><?= e($erreurs['envoi']) ?></p>
      <?php endif; ?>
      <p hidden aria-hidden="true"><label>Ne pas remplir <input type="text" name="site" tabindex="-1" autocomplete="off"></label></p>
      <div class="rangee">
        <div class="champ">
          <label for="f-nom">Nom *</label>
          <input id="f-nom" type="text" name="nom" autocomplete="family-name" required value="<?= e($valeurs['nom'] ?? '') ?>">
          <?php if (!empty($erreurs['nom'])): ?><span class="erreur"><?= e($erreurs['nom']) ?></span><?php endif; ?>
        </div>
        <div class="champ">
          <label for="f-prenom">Prénom</label>
          <input id="f-prenom" type="text" name="prenom" autocomplete="given-name" value="<?= e($valeurs['prenom'] ?? '') ?>">
        </div>
      </div>
      <div class="rangee">
        <div class="champ">
          <label for="f-email">E-mail *</label>
          <input id="f-email" type="email" name="email" autocomplete="email" required value="<?= e($valeurs['email'] ?? '') ?>">
          <?php if (!empty($erreurs['email'])): ?><span class="erreur"><?= e($erreurs['email']) ?></span><?php endif; ?>
        </div>
        <div class="champ">
          <label for="f-tel">Téléphone</label>
          <input id="f-tel" type="tel" name="tel" autocomplete="tel" value="<?= e($valeurs['tel'] ?? '') ?>">
        </div>
      </div>
      <div class="champ">
        <label for="f-message">Message *</label>
        <textarea id="f-message" name="message" rows="7" required><?= e($valeurs['message'] ?? '') ?></textarea>
        <?php if (!empty($erreurs['message'])): ?><span class="erreur"><?= e($erreurs['message']) ?></span><?php endif; ?>
      </div>
      <button class="btn btn--or" type="submit">Envoyer</button>
    </form>
  </div>
</section>

<?= $view->partial('bande-cta') ?>
