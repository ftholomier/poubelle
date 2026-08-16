<?php
/**
 * Confirmation d'envoi du formulaire.
 * @var array $valeurs
 * @var App\Core\View $view
 */
?>
<?= $view->partial('hero-page', ['hero' => [
    'image'    => 'assets/img/site/t-frey-9228-1.jpg',
    'surtitre' => 'Le domaine',
    'titre'    => 'Merci ' . ($valeurs['prenom'] ?: $valeurs['nom']) . ' !',
]]) ?>

<section class="section section--noire">
  <div class="conteneur conteneur--etroit centre">
    <div class="msg-succes reveler">
      <p>Votre demande a bien été enregistrée. Nous vous répondrons dans les meilleurs délais.</p>
    </div>
    <a class="btn btn--contour" href="<?= url('/') ?>" style="margin-top:2.4rem;">Retour à l'accueil</a>
  </div>
</section>
