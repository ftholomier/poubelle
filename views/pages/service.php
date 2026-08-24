<?php
/**
 * Fiche d'un service.
 *
 * @var array $item
 * @var array $autres        les autres services publiés
 * @var array $realisations  les chantiers rattachés à cette gamme
 * @var App\Core\View $view
 */
$hero = ($item['hero'] ?? []) + ['image' => $item['image'] ?? '', 'titre' => $item['nom'] ?? ''];
?>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<section class="section">
  <div class="conteneur conteneur--etroit">
    <p class="chapo reveler"><?= e($item['resume'] ?? '') ?></p>

    <?php foreach ($item['sections'] ?? [] as $sec): ?>
      <div class="bloc-texte reveler">
        <?php if (!empty($sec['titre'])): ?><h2><?= e($sec['titre']) ?></h2><?php endif; ?>

        <?php foreach ($sec['paragraphes'] ?? [] as $p): ?>
          <p><?= e($p) ?></p>
        <?php endforeach; ?>

        <?php if (!empty($sec['liste'])): ?>
          <ul class="liste-cochee">
            <?php foreach ($sec['liste'] as $ligne): ?>
              <li><?= e($ligne) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if (($realisations ?? []) !== []): ?>
<?php /* Les chantiers de cette gamme, et eux seuls : c'est ce qu'un visiteur
         venu lire une fiche produit veut voir juste après la fiche technique.
         Le lien de bas de section renvoie vers la galerie entière. */ ?>
<section class="section section--teinte">
  <div class="conteneur">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e(t('Réalisations')) ?></p>
      <h2 class="titre-section">
        <?= e(t('Nos chantiers en')) ?> <?= e(mb_strtolower((string) ($item['nom'] ?? ''))) ?>
      </h2>
      <p class="section__chapo">
        <?= count($realisations) ?> <?= count($realisations) > 1 ? e(t('réalisations posées par nos équipes.')) : e(t('réalisation posée par nos équipes.')) ?>
      </p>
    </div>

    <?= $view->partial('galerie', ['items' => $realisations, 'etiquettes' => false]) ?>

    <p class="section__pied reveler">
      <a class="btn btn--contour" href="<?= route('realisations') ?>">
        <?= e(t('Voir toutes les réalisations')) ?>
      </a>
    </p>
  </div>
</section>
<?php endif; ?>

<?php if ($autres !== []): ?>
<section class="section section--teinte">
  <div class="conteneur">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e(t('Nos autres gammes')) ?></p>
      <h2 class="titre-section"><?= e(t('Tout ce que nous fabriquons')) ?></h2>
    </div>

    <ul class="cartes cartes--services">
      <?php foreach ($autres as $service): ?>
        <li class="carte-service reveler">
          <a href="<?= route('nos-services', $service['slug']) ?>">
            <figure class="carte-service__media">
              <img src="<?= image($service['image'] ?? '', true) ?>" alt="<?= e($service['nom']) ?>" loading="lazy">
              <span class="carte-service__icone" aria-hidden="true">
                <?= $view->partial('icones', ['nom' => $service['icone'] ?? 'pergola-bioclimatique']) ?>
              </span>
            </figure>
            <h3 class="carte-service__titre"><?= e($service['nom']) ?></h3>
            <p class="carte-service__texte"><?= e($service['resume']) ?></p>
            <span class="carte-service__lien lien-fleche"><?= e(t('En savoir plus')) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?= $view->partial('bande-cta') ?>
