<?php
/**
 * Boutique : ferme familiale et vente directe.
 *
 * @var array $page
 * @var App\Core\View $view
 */
use App\Core\Liste;

?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<section class="section section--ivoire">
  <div class="conteneur">
    <div class="duo">
      <div class="duo__medias reveler">
        <img class="im-1" src="<?= image($page['histoire']['images'][0]) ?>" alt="La ferme de l'Étang Fourchu" loading="lazy">
        <?php if (!empty($page['histoire']['images'][1])): ?>
          <img class="im-2" src="<?= image($page['histoire']['images'][1], true) ?>" alt="" loading="lazy">
        <?php endif; ?>
      </div>
      <div class="duo__texte reveler" data-delai="1">
        <p class="surtitre"><?= e(t('Notre histoire')) ?></p>
        <h2 class="titre-section" style="font-size:clamp(1.6rem,2.9vw,2.2rem);"><?= e($page['histoire']['titre']) ?></h2>
        <hr class="filet-or">
        <?php foreach ($page['histoire']['paragraphes'] as $p): ?>
          <p><?= e($p) ?></p>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="duo duo--inverse" style="margin-top:clamp(3.5rem,7vw,5.5rem);">
      <div class="duo__medias reveler">
        <img class="im-1" src="<?= image($page['bio_local']['image']) ?>" alt="Le magasin à la ferme" loading="lazy">
      </div>
      <div class="duo__texte reveler" data-delai="1">
        <h2 class="titre-section" style="font-size:clamp(1.6rem,2.9vw,2.2rem);"><?= e($page['production']['titre']) ?></h2>
        <hr class="filet-or">
        <?php foreach ($page['production']['paragraphes'] as $p): ?>
          <p><?= e($p) ?></p>
        <?php endforeach; ?>
        <h3 style="margin-top:2rem;font-size:1.4rem;"><?= e($page['bio_local']['titre']) ?></h3>
        <?php foreach ($page['bio_local']['paragraphes'] as $p): ?>
          <p><?= e($p) ?></p>
        <?php endforeach; ?>
      </div>
    </div>

    <p class="bandeau-horaires reveler"><?= e($page['horaires']) ?></p>
  </div>
</section>

<section class="section section--noire">
  <div class="conteneur">
    <div class="centre">
      <p class="surtitre surtitre--centre reveler"><?= e(t('Vente directe')) ?></p>
      <h2 class="titre-section reveler"><?= e(t('Nos produits')) ?></h2>
      <hr class="filet-or filet-or--centre">
    </div>
    <div class="produits">
      <?php foreach (Liste::publiees($page['produits']) as $i => $prod): ?>
        <article class="produit reveler" data-delai="<?= ($i % 3) + 1 ?>">
          <div class="produit__media">
            <img src="<?= image($prod['image'], true) ?>" alt="<?= e($prod['nom']) ?>" loading="lazy">
          </div>
          <div class="produit__corps">
            <h3><?= e($prod['nom']) ?></h3>
            <p><?= e($prod['texte']) ?></p>
            <?php if (!empty($prod['details'])): ?>
              <ul>
                <?php foreach ($prod['details'] as $d): ?>
                  <li><?= e($d) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <a class="lien-fleche" href="<?= route('contact') ?>" style="margin-top:auto;">Réserver en ligne</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?= $view->partial('bande-cta') ?>
