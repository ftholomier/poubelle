<?php
/**
 * Liste des actualités, de la plus récente à la plus ancienne.
 *
 * La première occupe toute la largeur : sur un site de mairie, l'actualité du
 * moment est souvent la seule raison de la visite, et la reléguer au rang de
 * vignette parmi d'autres la fait manquer.
 *
 * @var array $page
 * @var array $items
 * @var App\Core\View $view
 */
$hero = ($page['hero'] ?? []) + ['titre' => $page['titre'] ?? '', 'image' => ''];
$une   = $items[0] ?? null;
$suite = array_slice($items, 1);
?>
<?php /* Le repère « vu » avance dès qu'on arrive ici, quel que soit le chemin :
         la pastille de l'en-tête, le menu, un lien dans un texte. C'est
         d'avoir vu la page qui compte. */ ?>
<span data-page-actualites hidden></span>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<?php if (!empty($page['sous_titre'])): ?>
<section class="section section--chapo">
  <div class="conteneur conteneur--etroit">
    <p class="chapo reveler"><?= e($page['sous_titre']) ?></p>
  </div>
</section>
<?php endif; ?>

<?php if ($une === null): ?>
<section class="section">
  <div class="conteneur conteneur--etroit">
    <p class="vide reveler"><?= e(t('Aucune actualité n’est publiée pour le moment.')) ?></p>
  </div>
</section>
<?php else: ?>

<section class="section">
  <div class="conteneur">
    <article class="une reveler">
      <a class="une__lien" href="<?= route('actualites', $une['slug']) ?>">
        <div class="une__media">
          <img src="<?= image($une['image'] ?? '') ?>" alt="<?= e($une['image_alt'] ?? '') ?>">
        </div>
        <div class="une__corps">
          <p class="surtitre"><?= e(date_texte((string) ($une['date'] ?? ''))) ?></p>
          <h2 class="une__titre"><?= e($une['titre'] ?? '') ?></h2>
          <p class="une__texte"><?= e($une['resume'] ?? '') ?></p>
          <span class="lien-fleche"><?= e(t('Lire la suite')) ?></span>
        </div>
      </a>
    </article>
  </div>
</section>

<?php if ($suite !== []): ?>
<section class="section section--teinte">
  <div class="conteneur">
    <ul class="cartes cartes--actus">
      <?php foreach ($suite as $item): ?>
        <li class="carte-actu reveler">
          <a href="<?= route('actualites', $item['slug']) ?>">
            <figure class="carte-actu__media">
              <img src="<?= image($item['image'] ?? '', true) ?>" alt="<?= e($item['image_alt'] ?? '') ?>" loading="lazy">
            </figure>
            <p class="carte-actu__date"><?= e(date_texte((string) ($item['date'] ?? ''))) ?></p>
            <h3 class="carte-actu__titre"><?= e($item['titre'] ?? '') ?></h3>
            <p class="carte-actu__texte"><?= e($item['resume'] ?? '') ?></p>
            <span class="carte-actu__lien lien-fleche"><?= e(t('Lire la suite')) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>
<?php endif; ?>

<?= $view->partial('sections', ['sections' => $page['sections'] ?? [], 'depart' => $suite !== [] ? 'teinte' : 'blanc']) ?>

<?= $view->partial('bande-cta') ?>
