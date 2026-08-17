<?php
/**
 * Fiche hébergement (Lodge Carélie, Lodge L'Indus, Le Gîte).
 *
 * @var array $item
 * @var App\Core\View $view
 * @var App\Core\Content $content
 */
$site = $content->load('site');
$resaUrl = $site['reservation']['hebergement']['url'];
?>
<?= $view->partial('hero-page', ['hero' => [
    // la photo propre à la fiche d'abord : images_avant est une bande de
    // trois vues, parfois partagée entre deux hébergements
    'image'    => $item['image'] ?: ($item['images_avant'][0] ?? ''),
    'surtitre' => 'À partir de ' . $item['prix_a_partir_de'] . ' € / nuit — ' . $item['capacite'] . ' personnes',
    'titre'    => $item['nom'],
    'texte'    => $item['sous_titre'] ?? '',
]]) ?>

<section class="section section--noire">
  <div class="conteneur">
    <?php foreach ($item['sections'] as $i => $sec): $inverse = $i % 2 === 1; ?>
      <div class="duo<?= $inverse ? ' duo--inverse' : '' ?>" style="margin-top:<?= $i === 0 ? '0' : 'clamp(3.5rem, 7vw, 5.5rem)' ?>;">
        <?php if (!empty($sec['images'])): ?>
          <div class="duo__medias reveler">
            <img class="im-1" src="<?= image($sec['images'][0]) ?>" alt="<?= e($item['nom']) ?> — <?= e($sec['titre']) ?>" loading="lazy">
            <?php if (!empty($sec['images'][1])): ?>
              <img class="im-2" src="<?= image($sec['images'][1], true) ?>" alt="" loading="lazy">
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="duo__medias reveler">
            <img class="im-1" src="<?= image($item['images_avant'][1] ?? $item['image']) ?>" alt="<?= e($item['nom']) ?>" loading="lazy">
          </div>
        <?php endif; ?>
        <div class="duo__texte reveler" data-delai="1">
          <h2 class="titre-section" style="font-size:clamp(1.7rem,3vw,2.4rem);"><?= e($sec['titre']) ?></h2>
          <hr class="filet-or">
          <?php foreach ($sec['paragraphes'] as $p): ?>
            <p><?= e($p) ?></p>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="centre reveler" style="margin-top:4rem;">
      <a class="btn btn--or" href="<?= e($resaUrl) ?>" target="_blank" rel="noopener">Réservation</a>
    </div>
  </div>
</section>

<section class="section section--noire-2">
  <div class="conteneur">
    <div class="centre">
      <p class="surtitre surtitre--centre reveler"><?= e(t('Galerie')) ?></p>
      <h2 class="titre-section reveler"><?= e($item['theme']['titre']) ?></h2>
      <hr class="filet-or filet-or--centre">
    </div>
    <div class="galerie-grille reveler">
      <?php foreach ($item['theme']['galerie'] as $img): ?>
        <a href="<?= asset($img) ?>" data-visionneuse data-alt="<?= e($item['nom']) ?>">
          <img src="<?= image($img, true) ?>" alt="<?= e($item['nom']) ?>" loading="lazy">
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section--ivoire">
  <div class="conteneur conteneur--etroit">
    <div class="centre">
      <p class="surtitre surtitre--centre reveler"><?= e(t('Confort')) ?></p>
      <h2 class="titre-section reveler"><?= e($item['prestations']['titre']) ?></h2>
      <hr class="filet-or filet-or--centre">
      <p class="reveler"><?= e($item['prestations']['intro']) ?></p>
    </div>

    <?php foreach ($item['prestations']['groupes'] as $groupe): ?>
      <div class="reveler" style="margin-top:2.2rem;">
        <?php if (!empty($groupe['titre'])): ?>
          <h3 style="font-size:1.3rem;margin-bottom:.9rem;"><?= e($groupe['titre']) ?></h3>
        <?php endif; ?>
        <ul class="liste-or">
          <?php foreach ($groupe['items'] as $eq): ?>
            <li><?= e($eq) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>

    <div class="bloc-texte reveler">
      <?php foreach ($item['prestations']['complement'] as $p): ?>
        <p><?= e($p) ?></p>
      <?php endforeach; ?>
    </div>

    <div class="reveler" style="margin-top:3rem;">
      <h3 style="font-size:1.35rem;"><?= e($item['supplements']['titre']) ?></h3>
      <ul class="liste-or" style="grid-template-columns:1fr;margin-top:1rem;">
        <?php foreach ($item['supplements']['items'] as $s): ?>
          <li><?= e($s) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="reveler" style="margin-top:3rem;">
      <h3 style="font-size:1.35rem;"><?= e($item['activites']['titre']) ?></h3>
      <p style="margin-top:.7rem;"><?= e($item['activites']['intro']) ?></p>
      <ul class="liste-or" style="margin-top:1rem;">
        <?php foreach ($item['activites']['items'] as $a): ?>
          <li><?= e($a) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="encadre reveler" style="border-color:rgba(165,129,58,.5);background:rgba(201,162,75,.09);">
      <h3 style="color:var(--or-texte);"><?= e(t('Tarification')) ?></h3>
      <p style="font-family:var(--serif);font-size:1.3rem;"><?= e($item['tarification']['texte']) ?></p>
      <p style="font-size:.98rem;font-style:italic;"><?= e($item['tarification']['note']) ?></p>
    </div>

    <div class="centre reveler" style="margin-top:3rem;">
      <a class="btn btn--or" href="<?= e($resaUrl) ?>" target="_blank" rel="noopener">Réserver en ligne</a>
    </div>
  </div>
</section>

<?= $view->partial('bande-cta') ?>
