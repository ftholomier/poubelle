<?php
/**
 * Plan d'accès d'une implantation, soumis au consentement.
 *
 * Un plan interactif est servi par un tiers : l'afficher d'office
 * déposerait ses cookies avant tout accord. Le cadre reste donc vide tant
 * que la catégorie « Contenus externes » n'est pas acceptée, et il porte à
 * la place un bouton qui laisse décider au cas par cas.
 *
 * Le lien d'itinéraire, lui, est toujours là : c'est un lien sortant, il ne
 * dépose rien et il rend le service attendu même si le plan reste fermé.
 *
 * @var array  $implantation  nom, role, rue, cp, ville, carte{embed, lien}
 * @var App\Core\View $view
 */
$carte = $implantation['carte'] ?? [];
$adresse = trim(($implantation['rue'] ?? '') . ', ' . ($implantation['cp'] ?? '') . ' ' . ($implantation['ville'] ?? ''));
?>
<article class="implantation reveler">
  <?php /* La politique de sécurité n'autorise que les cadres réellement
           montés : le fragment déclare donc l'hôte du plan, plutôt qu'une
           liste tenue ailleurs qui se périmerait au premier changement
           d'adresse fait depuis le back-office. */ ?>
  <?php if (($carte['embed'] ?? '') !== ''): App\Core\Entetes::autoriserCadre((string) $carte['embed']); ?>
    <div class="implantation__plan" data-cookies-contenu="externes">
      <div class="implantation__attente">
        <span class="implantation__attente-icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'adresse']) ?></span>
        <p class="implantation__attente-texte">
          <?= e(t('Le plan est fourni par un service externe qui dépose ses propres cookies.')) ?>
        </p>
        <button type="button" class="btn btn--contour btn--petit" data-cookies-accepter="externes">
          <?= e(t('Afficher le plan')) ?>
        </button>
      </div>
      <?php /* Le cadre n'est monté qu'au consentement : tant qu'il dort dans
               un <template>, aucune requête ne part. */ ?>
      <template>
        <iframe class="implantation__cadre" src="<?= e($carte['embed']) ?>"
                title="<?= e(t('Plan d’accès')) ?> — <?= e($implantation['nom'] ?? '') ?>"
                loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
      </template>
    </div>
  <?php endif; ?>

  <div class="implantation__corps">
    <?php if (($implantation['role'] ?? '') !== ''): ?>
      <p class="surtitre"><?= e($implantation['role']) ?></p>
    <?php endif; ?>
    <h3 class="implantation__titre"><?= e($implantation['nom'] ?? '') ?></h3>
    <address class="implantation__adresse">
      <?= e($implantation['rue'] ?? '') ?><br>
      <?= e($implantation['cp'] ?? '') ?> <?= e($implantation['ville'] ?? '') ?>
    </address>
    <?php if (($carte['lien'] ?? '') !== ''): ?>
      <a class="lien-fleche" href="<?= e($carte['lien']) ?>" target="_blank" rel="noopener">
        <?= e(t('Itinéraire')) ?><span class="sr-only"> — <?= e($adresse) ?></span>
      </a>
    <?php endif; ?>
  </div>
</article>
