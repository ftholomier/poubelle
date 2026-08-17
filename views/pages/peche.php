<?php
/**
 * Page pêche : présentation des deux étangs.
 *
 * @var array $page
 * @var array $items
 * @var App\Core\View $view
 * @var App\Core\Content $content
 */
$peche = $content->load('peche');
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<section class="section section--noire">
  <div class="conteneur">
    <div class="centre conteneur--etroit reveler" style="margin-inline:auto;">
      <p class="heros__texte" style="margin:0 auto;color:rgba(244,239,230,.82);"><?= e($peche['intro']['texte']) ?></p>
    </div>

    <?php foreach ($items as $i => $et): $inverse = $i % 2 === 1; ?>
      <div class="duo<?= $inverse ? ' duo--inverse' : '' ?>" style="margin-top:clamp(3.5rem, 7vw, 5.5rem);">
        <div class="duo__medias reveler">
          <img class="im-1" src="<?= image($et['image']) ?>" alt="<?= e($et['nom']) ?>" loading="lazy" style="aspect-ratio:4/3;">
        </div>
        <div class="duo__texte reveler" data-delai="1">
          <p class="surtitre"><?= e($et['sous_titre']) ?></p>
          <h2 class="titre-section"><?= e($et['nom']) ?></h2>
          <hr class="filet-or">
          <p><?= e($et['resume']) ?></p>
          <a class="btn btn--contour" href="<?= url('/peche/' . $et['slug']) ?>">Découvrir</a>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="centre reveler" style="margin-top:4rem;">
      <a class="lien-fleche" href="<?= url('/reglement-general') ?>">Consulter le règlement général</a>
    </div>
  </div>
</section>

<?= $view->partial('bande-cta') ?>
