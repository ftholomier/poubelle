<?php
/**
 * Page d'accueil.
 *
 * @var array $page
 * @var array $hebergements
 * @var array $etangs
 * @var App\Core\View $view
 * @var App\Core\Content $content
 */
$site = $content->load('site');
$resa = $site['reservation'];
$hero = $page['hero'];
?>
<section class="heros">
  <div class="heros__fond" style="background-image:url('<?= image($hero['image']) ?>')"></div>
  <div class="heros__contenu">
    <p class="surtitre surtitre--centre"><?= e($hero['surtitre']) ?></p>
    <h1 class="heros__titre"><?= e($hero['titre']) ?></h1>
    <p class="heros__texte"><?= e($hero['texte']) ?></p>
    <div class="heros__actions">
      <a class="btn btn--or" href="<?= e($resa['hebergement']['url']) ?>" target="_blank" rel="noopener"><?= e($resa['hebergement']['libelle']) ?></a>
      <a class="btn btn--contour" href="<?= e($resa['etang']['url']) ?>" target="_blank" rel="noopener"><?= e($resa['etang']['libelle']) ?></a>
    </div>
  </div>
  <a class="heros__defiler" href="#decouvrir">Découvrir</a>
</section>

<section class="section section--ivoire" id="decouvrir">
  <div class="conteneur centre">
    <p class="surtitre surtitre--centre reveler"><?= e($page['piliers']['surtitre']) ?></p>
    <h2 class="titre-section reveler"><?= e($page['piliers']['titre']) ?></h2>
    <hr class="filet-or filet-or--centre">
    <div class="piliers">
      <?php foreach ($page['piliers']['items'] as $i => $pilier): ?>
        <div class="pilier reveler" data-delai="<?= $i + 1 ?>">
          <div class="pilier__icone"><?= $view->partial('icones', ['nom' => $pilier['icone']]) ?></div>
          <p><?= e($pilier['texte']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="reveler" style="margin-top:3rem;font-family:var(--serif);font-size:1.35rem;"><?= e($page['piliers']['cta']) ?></p>
    <div class="heros__actions reveler">
      <a class="btn btn--contour-sombre" href="<?= e($resa['hebergement']['url']) ?>" target="_blank" rel="noopener"><?= e($resa['hebergement']['libelle']) ?></a>
      <a class="btn btn--contour-sombre" href="<?= e($resa['etang']['url']) ?>" target="_blank" rel="noopener"><?= e($resa['etang']['libelle']) ?></a>
    </div>
  </div>
</section>

<section class="section section--noire">
  <div class="conteneur">
    <div class="centre">
      <p class="surtitre surtitre--centre reveler"><?= e($page['activites']['surtitre']) ?></p>
      <h2 class="titre-section reveler"><?= e($page['activites']['titre']) ?></h2>
      <hr class="filet-or filet-or--centre">
    </div>
    <div class="cartes">
      <?php foreach ($page['activites']['items'] as $i => $act): ?>
        <article class="carte reveler" data-delai="<?= $i + 1 ?>">
          <div class="carte__media">
            <img src="<?= image($act['image']) ?>" alt="<?= e($act['titre']) ?>" loading="lazy">
          </div>
          <div class="carte__corps">
            <h3 class="carte__titre"><?= e($act['titre']) ?></h3>
            <p><?= e($act['texte']) ?></p>
            <a class="lien-fleche" href="<?= url($act['url']) ?>">En savoir plus</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section--noire-2">
  <div class="conteneur">
    <div class="centre">
      <p class="surtitre surtitre--centre reveler">Hébergements</p>
      <h2 class="titre-section reveler">Lodges &amp; Gîte de charme</h2>
      <hr class="filet-or filet-or--centre">
    </div>
    <div class="cartes">
      <?php foreach ($hebergements as $i => $h): ?>
        <article class="carte reveler" data-delai="<?= $i + 1 ?>">
          <div class="carte__media">
            <img src="<?= image($h['image']) ?>" alt="<?= e($h['nom']) ?>" loading="lazy">
          </div>
          <div class="carte__corps">
            <p class="carte__prix">À partir de <?= e($h['prix_a_partir_de']) ?> € / nuit</p>
            <h3 class="carte__titre"><?= e($h['nom']) ?></h3>
            <p><?= e(mb_strimwidth($h['resume'], 0, 190, '…')) ?></p>
            <a class="lien-fleche" href="<?= route('hebergements', $h['slug']) ?>">Découvrir</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="citation" style="background-image:url('<?= image($page['citation']['image']) ?>')">
  <div class="conteneur reveler">
    <h2 class="citation__titre">« <?= e($page['citation']['titre']) ?> »</h2>
    <p><?= e($page['citation']['texte']) ?></p>
    <a class="btn btn--contour" href="<?= route('contact') ?>">Contactez-nous</a>
  </div>
</section>

<section class="section section--ivoire">
  <div class="conteneur">
    <div class="centre">
      <p class="surtitre surtitre--centre reveler"><?= e($page['ferme']['surtitre']) ?></p>
      <h2 class="titre-section reveler"><?= e($page['ferme']['titre']) ?></h2>
      <hr class="filet-or filet-or--centre">
    </div>
    <div class="mosaique reveler">
      <?php foreach ($page['ferme']['images'] as $img): ?>
        <a href="<?= asset($img) ?>" data-visionneuse data-alt="La ferme — vente directe">
          <img src="<?= image($img, true) ?>" alt="La ferme — vente directe" loading="lazy">
        </a>
      <?php endforeach; ?>
    </div>
    <div class="centre reveler" style="margin-top:2.6rem;">
      <h3 style="font-size:1.35rem;"><?= e($page['ferme']['sous_titre']) ?></h3>
      <ul class="liste-or" style="max-width:820px;margin-inline:auto;text-align:left;">
        <?php foreach ($page['ferme']['produits'] as $p): ?>
          <li><?= e($p) ?></li>
        <?php endforeach; ?>
      </ul>
      <a class="btn btn--contour-sombre" href="<?= route('boutique') ?>" style="margin-top:2.4rem;">Découvrir les produits</a>
    </div>
  </div>
</section>

<?= $view->partial('bande-cta') ?>
