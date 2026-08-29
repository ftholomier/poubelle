<?php
/**
 * Page de contenu générique : la plupart des pages du site passent par ici.
 *
 * Le contenu est une suite de blocs typés, rendus par views/partials/sections.php.
 * Le bandeau photo tient lieu de section sombre : l'alternance démarre donc
 * sur un fond clair.
 *
 * @var array $page
 * @var App\Core\View $view
 */
$hero = ($page['hero'] ?? []) + ['titre' => $page['titre'] ?? '', 'image' => ''];

?>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<?php if (!empty($page['sous_titre'])): ?>
<section class="section section--chapo">
  <div class="conteneur conteneur--etroit">
    <p class="chapo reveler"><?= e($page['sous_titre']) ?></p>
  </div>
</section>
<?php endif; ?>

<?= $view->partial('sections', ['sections' => $page['sections'] ?? [], 'depart' => 'sombre']) ?>

<?= $view->partial('bande-cta') ?>
