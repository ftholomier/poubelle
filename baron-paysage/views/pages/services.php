<?php
/**
 * Page « Nos services » : liste des prestations.
 *
 * @var array $page
 * @var array $items
 * @var App\Core\View $view
 */
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<section class="section">
  <div class="conteneur">
    <ul class="cartes cartes--services cartes--services-large">
      <?php foreach ($items as $service): ?>
        <li class="carte-service reveler">
          <a href="<?= route('nos-services', $service['slug']) ?>">
            <figure class="carte-service__media">
              <img src="<?= image($service['image'] ?? '', true) ?>" alt="<?= e($service['nom']) ?>" loading="lazy">
              <span class="carte-service__icone" aria-hidden="true">
                <?= $view->partial('icones', ['nom' => $service['icone'] ?? 'feuille']) ?>
              </span>
            </figure>
            <h2 class="carte-service__titre"><?= e($service['nom']) ?></h2>
            <p class="carte-service__texte"><?= e($service['resume']) ?></p>
            <span class="carte-service__lien lien-fleche"><?= e(t('En savoir plus')) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<?php foreach ($page['sections'] ?? [] as $sec): ?>
<section class="section section--teinte">
  <div class="conteneur conteneur--etroit">
    <div class="bloc-texte reveler">
      <?php if (!empty($sec['titre'])): ?><h2 class="titre-section"><?= e($sec['titre']) ?></h2><?php endif; ?>
      <?php foreach ($sec['paragraphes'] ?? [] as $p): ?>
        <p><?= e($p) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endforeach; ?>

<?php if (!empty($page['methode']['etapes'])): ?>
<section class="section">
  <div class="conteneur">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e($page['methode']['surtitre']) ?></p>
      <h2 class="titre-section"><?= e($page['methode']['titre']) ?></h2>
    </div>

    <ol class="etapes">
      <?php foreach ($page['methode']['etapes'] as $etape): ?>
        <li class="etape reveler">
          <span class="etape__numero"><?= e($etape['numero']) ?></span>
          <h3 class="etape__titre"><?= e($etape['titre']) ?></h3>
          <p><?= e($etape['texte']) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php endif; ?>

<?= $view->partial('bande-cta') ?>
