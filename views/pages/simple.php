<?php
/**
 * Page de contenu générique : mentions légales, et toute page éditoriale
 * qui ne demande pas de mise en page particulière.
 *
 * @var array $page
 * @var App\Core\View $view
 */
$hero = ($page['hero'] ?? []) + ['titre' => $page['titre'], 'image' => ''];
?>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<section class="section">
  <div class="conteneur conteneur--etroit">
    <?php if (!empty($page['sous_titre'])): ?>
      <p class="chapo reveler"><?= e($page['sous_titre']) ?></p>
    <?php endif; ?>

    <?php foreach ($page['sections'] ?? [] as $sec): ?>
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

<?= $view->partial('bande-cta') ?>
