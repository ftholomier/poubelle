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

    <ul class="galerie" data-galerie>
      <?php foreach ($items as $i => $item): ?>
        <?php $legende = trim((string) ($item['legende'] ?? '')) ?: (string) ($item['nom'] ?? ''); ?>
        <li class="galerie__item reveler" data-categorie="<?= e($item['categorie'] ?? '') ?>">
          <button type="button" class="galerie__vignette"
                  data-visionneuse="<?= e(image($item['image'])) ?>"
                  data-legende="<?= e($legende) ?>"
                  aria-label="<?= e(t('Agrandir')) ?> — <?= e($legende) ?>">
            <img src="<?= image($item['image'], true) ?>" alt="<?= e($legende) ?>"
                 loading="<?= $i < 8 ? 'eager' : 'lazy' ?>" decoding="async">
            <?php if (($item['categorie'] ?? '') !== ''): ?>
              <span class="galerie__etiquette"><?= e($item['categorie']) ?></span>
            <?php endif; ?>
          </button>
        </li>
      <?php endforeach; ?>
    </ul>

    <p class="galerie__vide" data-galerie-vide hidden><?= e(t('Aucune réalisation dans cette catégorie.')) ?></p>
  </div>
</section>

<?php /* Visionneuse : un seul dialogue réutilisé pour toutes les photos,
         plutôt qu'un par vignette. Elle est vide au chargement et ne pèse
         donc rien tant qu'on ne l'ouvre pas. */ ?>
<div class="visionneuse" data-visionneuse-boite hidden role="dialog" aria-modal="true" aria-label="<?= e(t('Photo agrandie')) ?>">
  <button type="button" class="visionneuse__fermer" data-visionneuse-fermer aria-label="<?= e(t('Fermer')) ?>"></button>
  <button type="button" class="visionneuse__nav visionneuse__nav--avant" data-visionneuse-avant aria-label="<?= e(t('Photo précédente')) ?>"></button>
  <figure class="visionneuse__cadre">
    <img alt="" data-visionneuse-image>
    <figcaption data-visionneuse-legende></figcaption>
  </figure>
  <button type="button" class="visionneuse__nav visionneuse__nav--apres" data-visionneuse-apres aria-label="<?= e(t('Photo suivante')) ?>"></button>
</div>

<?= $view->partial('bande-cta') ?>
