<?php
/**
 * Confirmation d'envoi, commune aux deux formulaires du site.
 *
 * Seule la phrase d'attente les distingue : une demande en ligne annonce une
 * visite sur place, un message annonce une réponse. Promettre la visite à qui
 * a seulement posé une question serait un contresens.
 *
 * @var array $valeurs
 * @var string $reponse  ce à quoi le visiteur doit s'attendre
 * @var App\Core\Content $content
 * @var App\Core\View $view
 */
$site = $content->load('site');
$tel  = (string) ($site['contact']['telephone'] ?? '');
$nom  = trim((string) ($valeurs['prenom'] ?: $valeurs['nom']));
?>
<?= $view->partial('hero-page', ['hero' => [
    'image'    => 'assets/img/site/muret-parement-plate-bande.jpg',
    'surtitre' => $site['nom'],
    'titre'    => $nom !== '' ? 'Merci ' . $nom . ' !' : 'Merci !',
]]) ?>

<section class="section">
  <div class="conteneur conteneur--etroit centre">
    <div class="msg-succes reveler">
      <p><?= e($reponse) ?></p>
      <?php if ($tel !== ''): ?>
        <p><?= e(t('Pour une demande urgente, vous pouvez nous joindre au')) ?>
          <a href="<?= e(tel_lien($tel)) ?>"><?= e($tel) ?></a>.</p>
      <?php endif; ?>
    </div>

    <div class="erreur-page__actions">
      <a class="btn btn--contour" href="<?= route('accueil') ?>"><?= e(t('Retour à l’accueil')) ?></a>
      <a class="btn btn--bleu" href="<?= route('nos-services') ?>"><?= e(t('Découvrir nos services')) ?></a>
    </div>
  </div>
</section>
