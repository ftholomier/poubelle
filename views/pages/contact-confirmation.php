<?php
/**
 * Confirmation d'envoi du formulaire.
 *
 * @var array $valeurs
 * @var App\Core\Content $content
 * @var App\Core\View $view
 */
$site = $content->load('site');
$tel  = (string) ($site['contact']['telephone'] ?? '');
$nom  = trim((string) ($valeurs['prenom'] ?: $valeurs['nom']));
?>
<?= $view->partial('hero-page', ['hero' => [
    'image'    => 'assets/img/site/hero-contact.jpg',
    'surtitre' => $site['nom'],
    'titre'    => $nom !== '' ? 'Merci ' . $nom . ' !' : 'Merci !',
]]) ?>

<section class="section">
  <div class="conteneur conteneur--etroit centre">
    <div class="msg-succes reveler">
      <p><?= e(t('Votre demande a bien été enregistrée. Nous vous répondons sous un jour ouvré.')) ?></p>
      <?php if ($tel !== ''): ?>
        <p><?= e(t('Pour une demande urgente, vous pouvez nous joindre au')) ?>
          <a href="<?= e(tel_lien($tel)) ?>"><?= e($tel) ?></a>.</p>
      <?php endif; ?>
    </div>

    <div class="erreur-page__actions">
      <a class="btn btn--contour" href="<?= route('accueil') ?>"><?= e(t('Retour à l’accueil')) ?></a>
      <a class="btn btn--orange" href="<?= route('nos-services') ?>"><?= e(t('Découvrir nos services')) ?></a>
    </div>
  </div>
</section>
