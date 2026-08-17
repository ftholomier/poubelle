<?php
/**
 * Galerie photo filtrable.
 *
 * @var array $page
 * @var array $medias
 * @var App\Core\View $view
 */
$categories = [
  'tout'    => 'Tout',
  'domaine' => 'Le domaine',
  'carelie' => 'Lodge Carélie',
  'indus'   => "Lodge L'Indus",
  'gite'    => 'Le Gîte',
  'ferme'   => 'La ferme',
];
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<section class="section section--noire">
  <div class="conteneur">
    <div class="filtres reveler" role="group" aria-label="Filtrer la galerie">
      <?php foreach ($categories as $cle => $libelle): ?>
        <button data-filtre="<?= e($cle) ?>" aria-pressed="<?= $cle === 'tout' ? 'true' : 'false' ?>"><?= e($libelle) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="galerie-grille">
      <?php foreach ($medias as $m): ?>
        <a href="<?= asset($m['src']) ?>" data-visionneuse data-cat="<?= e($m['cat']) ?>" data-alt="<?= e($m['alt']) ?>">
          <img src="<?= image($m['src'], true) ?>" alt="<?= e($m['alt']) ?>" loading="lazy">
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?= $view->partial('bande-cta') ?>
