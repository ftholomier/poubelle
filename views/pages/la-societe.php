<?php
/**
 * Page « La société ».
 *
 * @var array $page
 * @var App\Core\View $view
 */
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<section class="section">
  <div class="conteneur conteneur--etroit">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e($page['histoire']['surtitre']) ?></p>
      <h2 class="titre-section"><?= e($page['histoire']['titre']) ?></h2>
    </div>
    <div class="bloc-texte reveler">
      <?php foreach ($page['histoire']['paragraphes'] as $p): ?>
        <p><?= e($p) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section--teinte">
  <div class="conteneur">
    <ul class="piliers">
      <?php foreach ($page['piliers']['items'] as $pilier): ?>
        <li class="pilier reveler">
          <figure class="pilier__media">
            <img src="<?= image($pilier['image']) ?>" alt="<?= e($pilier['titre']) ?>" loading="lazy">
          </figure>
          <div class="pilier__corps">
            <p class="pilier__entete">
              <span class="pilier__numero"><?= e($pilier['numero']) ?></span>
              <span class="pilier__label"><?= e($pilier['label']) ?></span>
            </p>
            <h3 class="pilier__titre"><?= e($pilier['titre']) ?></h3>
            <p><?= e($pilier['texte']) ?></p>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="section">
  <div class="conteneur">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e($page['clients']['surtitre']) ?></p>
      <h2 class="titre-section"><?= e($page['clients']['titre']) ?></h2>
      <p class="section__chapo"><?= e($page['clients']['texte']) ?></p>
    </div>

    <ul class="cartes cartes--clients">
      <?php foreach ($page['clients']['items'] as $client): ?>
        <li class="carte-client reveler">
          <span class="carte-client__icone" aria-hidden="true">
            <?= $view->partial('icones', ['nom' => $client['icone']]) ?>
          </span>
          <h3 class="carte-client__titre"><?= e($client['nom']) ?></h3>
          <p class="carte-client__texte"><?= e($client['texte']) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="section section--teinte">
  <div class="conteneur conteneur--etroit">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e($page['engagement']['surtitre']) ?></p>
      <h2 class="titre-section"><?= e($page['engagement']['titre']) ?></h2>
    </div>
    <div class="bloc-texte reveler">
      <?php foreach ($page['engagement']['paragraphes'] as $p): ?>
        <p><?= e($p) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?= $view->partial('bande-cta') ?>
