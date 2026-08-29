<?php
/**
 * Grille de photos et sa visionneuse.
 *
 * Partagée par la page « Réalisations » et par chaque page de gamme : le même
 * balisage, donc le même script et le même style, quel que soit l'endroit où
 * la galerie apparaît.
 *
 * @var array $items       fiches à montrer
 * @var bool  $etiquettes  afficher la catégorie sur chaque vignette
 * @var App\Core\View $view
 */
$etiquettes ??= true;
if (($items ?? []) === []) {
    return;
}
?>
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
        <?php /* Étiquette inutile quand toute la galerie est d'une même
                 catégorie : elle ne ferait que répéter le titre. */ ?>
        <?php if ($etiquettes && ($item['categorie'] ?? '') !== ''): ?>
          <span class="galerie__etiquette"><?= e($item['categorie']) ?></span>
        <?php endif; ?>
        <?php /* La légende n'est reprise sur la vignette que si elle dit
                 autre chose que la catégorie : sans quoi le survol
                 répéterait l'étiquette posée juste au-dessus. */ ?>
        <?php if ($legende !== '' && $legende !== ($item['categorie'] ?? '')): ?>
          <span class="galerie__legende"><?= e($legende) ?></span>
        <?php endif; ?>
      </button>
    </li>
  <?php endforeach; ?>
</ul>

<p class="galerie__vide" data-galerie-vide hidden><?= e(t('Aucune réalisation dans cette catégorie.')) ?></p>

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
