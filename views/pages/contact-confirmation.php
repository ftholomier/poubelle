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

/* La photo de la page Contact, et non un fichier écrit en dur.
   Celui qui l'était — « muret-parement-plate-bande.jpg », venu du site
   commercial dont ce socle est tiré — n'existe plus : image() servait donc
   « photo à venir » sur la page que le visiteur voit juste après avoir écrit à
   la mairie. Passer par le contenu la fait suivre la mairie le jour où elle
   change le bandeau de Contact, et ne laisse plus de nom de fichier à
   oublier. */
$photo = (string) ($content->get('pages/contact', 'hero.image', '')
                   ?: $content->get('pages/accueil', 'hero.image', ''));
?>
<?= $view->partial('hero-page', ['hero' => [
    'image'    => $photo,
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
      <?php /* « Nos services » était une rubrique du site commercial : la
               route n'existe pas ici, et le bouton menait à un 404. Une mairie
               renvoie vers ses démarches — c'est ce que le visiteur cherchait
               probablement avant d'écrire. */ ?>
      <a class="btn btn--bleu" href="<?= route('demarches') ?>"><?= e(t('Voir les démarches')) ?></a>
    </div>
  </div>
</section>
