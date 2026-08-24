<?php
/**
 * Page « Réalisations » : galerie filtrable.
 *
 * Le filtrage se fait côté navigateur, sur des fiches déjà présentes dans la
 * page : aucun appel réseau, aucun rechargement, et la galerie reste entière
 * pour les moteurs de recherche comme pour un visiteur sans JavaScript — les
 * boutons de filtre, eux, ne s'affichent que si le script a pris la main.
 *
 * @var array $page
 * @var array $items
 * @var string[] $categories
 * @var array $collection
 * @var App\Core\View $view
 */
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<section class="section">
  <div class="conteneur">
    <?php if (trim((string) ($collection['intro'] ?? '')) !== ''): ?>
      <p class="chapo reveler"><?= e($collection['intro']) ?></p>
    <?php endif; ?>

    <?php if (count($categories) > 1): ?>
      <div class="filtres" data-filtres hidden>
        <button type="button" class="filtres__bouton" data-filtre="" aria-pressed="true">
          <?= e(t('Tout voir')) ?>
          <span class="filtres__compte"><?= count($items) ?></span>
        </button>
        <?php foreach ($categories as $categorie): ?>
          <?php $n = count(array_filter($items, static fn(array $i): bool => ($i['categorie'] ?? '') === $categorie)); ?>
          <button type="button" class="filtres__bouton" data-filtre="<?= e($categorie) ?>" aria-pressed="false">
            <?= e($categorie) ?>
            <span class="filtres__compte"><?= $n ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?= $view->partial('galerie', ['items' => $items]) ?>
  </div>
</section>

<?= $view->partial('bande-cta') ?>
