<?php
/**
 * Liste des hébergements.
 *
 * @var array $page
 * @var array $items
 * @var App\Core\View $view
 * @var App\Core\Content $content
 */
$intro = $content->load('hebergements')['intro'] ?? null;
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<section class="section section--noire">
  <div class="conteneur">
    <?php if ($intro): ?>
      <div class="centre conteneur--etroit reveler" style="margin-inline:auto;">
        <p class="heros__texte" style="margin:0 auto;color:rgba(244,239,230,.82);"><?= e($intro['texte']) ?></p>
      </div>
    <?php endif; ?>

    <?php foreach ($items as $i => $h): $inverse = $i % 2 === 1; ?>
      <div class="duo<?= $inverse ? ' duo--inverse' : '' ?>" style="margin-top:<?= $i === 0 ? '3.4rem' : 'clamp(4rem, 8vw, 6rem)' ?>;">
        <div class="duo__medias reveler">
          <img class="im-1" src="<?= image($h['images_avant'][0] ?? $h['image']) ?>" alt="<?= e($h['nom']) ?>" loading="lazy">
          <?php if (!empty($h['images_avant'][2])): ?>
            <img class="im-2" src="<?= image($h['images_avant'][2], true) ?>" alt="" loading="lazy">
          <?php endif; ?>
        </div>
        <div class="duo__texte reveler" data-delai="1">
          <p class="surtitre">À partir de <?= e($h['prix_a_partir_de']) ?> € / nuit — <?= e($h['capacite']) ?> personnes</p>
          <h2 class="titre-section"><?= e($h['nom']) ?></h2>
          <hr class="filet-or">
          <p><?= e($h['resume']) ?></p>
          <a class="btn btn--contour" href="<?= url('/hebergements/' . $h['slug']) ?>">Découvrir</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?= $view->partial('bande-cta') ?>
