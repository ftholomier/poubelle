<?php
/**
 * Page de contenu générique : règlement, mentions légales.
 *
 * @var array $page
 * @var App\Core\View $view
 */
$hero = $page['hero'] ?? [
  'image'    => 'assets/img/site/image-9.jpg',
  'surtitre' => 'Le domaine',
  'titre'    => $page['titre'],
];
?>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<section class="section section--noire">
  <div class="conteneur conteneur--etroit">
    <?php if (!empty($page['sous_titre'])): ?>
      <p class="centre reveler" style="font-style:italic;color:rgba(244,239,230,.7);"><?= e($page['sous_titre']) ?></p>
    <?php endif; ?>

    <?php if (!empty($page['regles'])): ?>
      <ul class="liste-or reveler" style="grid-template-columns:1fr;margin-top:2.6rem;gap:1rem;">
        <?php foreach ($page['regles'] as $r): ?>
          <li><?= e($r) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if (!empty($page['conclusion'])): ?>
      <div class="encadre reveler">
        <?php foreach ($page['conclusion'] as $p): ?>
          <p><?= e($p) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php foreach ($page['sections'] ?? [] as $sec): ?>
      <div class="bloc-texte reveler">
        <?php if (!empty($sec['titre'])): ?><h2><?= e($sec['titre']) ?></h2><?php endif; ?>
        <?php foreach ($sec['paragraphes'] ?? [] as $p): ?>
          <p><?= e($p) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?= $view->partial('bande-cta') ?>
