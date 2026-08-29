<?php
/**
 * Article d'actualité.
 *
 * @var array $page
 * @var array $item
 * @var array $autres
 * @var App\Core\View $view
 */
$hero = ($item['hero'] ?? []) + [
    'titre'    => $item['titre'] ?? '',
    'surtitre' => date_texte((string) ($item['date'] ?? '')),
    'image'    => $item['image'] ?? '',
];
?>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<article class="section">
  <div class="conteneur conteneur--etroit">
    <?php if (!empty($item['resume'])): ?>
      <p class="chapo reveler"><?= e($item['resume']) ?></p>
    <?php endif; ?>
    <?php /* La date est répétée en clair sous le chapô : celle du bandeau est
             posée sur une photo, et l'administré qui imprime la page perd le
             bandeau. */ ?>
    <?php if (!empty($item['date'])): ?>
      <p class="article__date reveler">
        <time datetime="<?= e($item['date']) ?>"><?= e(t('Publié le')) ?> <?= e(date_texte((string) $item['date'], true)) ?></time>
      </p>
    <?php endif; ?>
  </div>
</article>

<?= $view->partial('sections', ['sections' => $item['sections'] ?? [], 'depart' => 'blanc']) ?>

<?php if ($autres !== []): ?>
<section class="section section--teinte">
  <div class="conteneur">
    <div class="section__tete reveler">
      <h2 class="titre-section"><?= e(t('Autres actualités')) ?></h2>
    </div>
    <ul class="cartes cartes--actus">
      <?php foreach ($autres as $autre): ?>
        <li class="carte-actu reveler">
          <a href="<?= route('actualites', $autre['slug']) ?>">
            <figure class="carte-actu__media">
              <img src="<?= image($autre['image'] ?? '', true) ?>" alt="<?= e($autre['image_alt'] ?? '') ?>" loading="lazy">
            </figure>
            <p class="carte-actu__date"><?= e(date_texte((string) ($autre['date'] ?? ''))) ?></p>
            <h3 class="carte-actu__titre"><?= e($autre['titre'] ?? '') ?></h3>
            <p class="carte-actu__texte"><?= e($autre['resume'] ?? '') ?></p>
            <span class="carte-actu__lien lien-fleche"><?= e(t('Lire la suite')) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="section__pied reveler">
      <a class="btn btn--contour" href="<?= route('actualites') ?>"><?= e(t('Toutes les actualités')) ?></a>
    </p>
  </div>
</section>
<?php endif; ?>

<?= $view->partial('bande-cta') ?>
