<?php
/**
 * Fiche étang (Fourchu / Florimont).
 *
 * @var array $item
 * @var App\Core\View $view
 * @var App\Core\Content $content
 */
$site   = $content->load('site');
$regles = $content->load('peche')['regles_communes'];
$resa   = $site['reservation']['etang'];
$tarif  = $item['tarification'];
?>
<?= $view->partial('hero-page', ['hero' => [
    'image'    => $item['image'],
    'surtitre' => 'Pêche sportive',
    'titre'    => $item['titre'],
    'texte'    => $item['sous_titre'],
]]) ?>

<section class="section section--noire">
  <div class="conteneur">
    <div class="duo">
      <div class="duo__medias reveler">
        <img class="im-1" src="<?= image($item['image_secondaire']) ?>" alt="<?= e($item['nom']) ?>" loading="lazy" style="aspect-ratio:4/3;">
      </div>
      <div class="duo__texte reveler" data-delai="1">
        <p class="surtitre"><?= e(t('Réservation')) ?></p>
        <h2 class="titre-section" style="font-size:clamp(1.7rem,3vw,2.4rem);"><?= e($item['reservation']['titre']) ?></h2>
        <hr class="filet-or">
        <?php foreach ($item['reservation']['paragraphes'] as $p): ?>
          <p><?= e($p) ?></p>
        <?php endforeach; ?>
        <a class="btn btn--or" href="<?= e($resa['url']) ?>" target="_blank" rel="noopener"><?= e($resa['libelle']) ?></a>
      </div>
    </div>
  </div>
</section>

<section class="section section--noire-2">
  <div class="conteneur conteneur--etroit">
    <div class="centre">
      <p class="surtitre surtitre--centre reveler"><?= e(t('Tarifs')) ?></p>
      <h2 class="titre-section reveler"><?= e($tarif['titre']) ?></h2>
      <hr class="filet-or filet-or--centre">
      <p class="reveler"><?= e($tarif['intro']) ?></p>
      <p class="reveler" style="margin-top:1.4rem;font-family:var(--garamond);font-weight:600;letter-spacing:.26em;text-transform:uppercase;font-size:.82rem;color:var(--or);"><?= e($tarif['saison']) ?></p>
      <p class="reveler" style="font-style:italic;font-size:1rem;"><?= e($tarif['precision']) ?></p>
    </div>

    <div class="table-defile reveler">
      <table class="tarifs">
        <thead>
          <tr>
            <?php foreach ($tarif['tableau']['entetes'] as $th): ?>
              <th scope="col"><?= e($th) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <tr>
            <?php foreach ($tarif['tableau']['valeurs'] as $td): ?>
              <td><?= e($td) ?></td>
            <?php endforeach; ?>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="centre reveler" style="margin-top:1.8rem;font-style:italic;"><?= e($tarif['attention']) ?></p>

    <?php if (!empty($item['specificites'])): ?>
      <div class="encadre reveler">
        <h3><?= e($item['specificites']['titre']) ?></h3>
        <ul class="liste-or" style="grid-template-columns:1fr;margin-top:.9rem;">
          <?php foreach ($item['specificites']['items'] as $s): ?>
            <li><?= e($s) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!empty($item['droits']) || !empty($item['interdictions'])): ?>
      <div class="deux-colonnes">
        <?php if (!empty($item['droits'])): ?>
          <div class="carte-regles reveler">
            <h3><?= e($item['droits']['titre']) ?></h3>
            <ul>
              <?php foreach ($item['droits']['items'] as $d): ?>
                <li><?= e($d) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <?php if (!empty($item['interdictions'])): ?>
          <div class="carte-regles reveler" data-delai="1">
            <h3><?= e($item['interdictions']['titre']) ?></h3>
            <ul>
              <?php foreach ($item['interdictions']['items'] as $d): ?>
                <li><?= e($d) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($item['recapitulatif'])): ?>
      <p class="bandeau-horaires reveler"><?= e($item['recapitulatif']) ?></p>
    <?php endif; ?>
  </div>
</section>

<section class="section section--noire">
  <div class="conteneur conteneur--etroit">
    <div class="centre">
      <p class="surtitre surtitre--centre reveler"><?= e($regles['surtitre']) ?></p>
      <h2 class="titre-section reveler"><?= e($regles['titre']) ?></h2>
      <hr class="filet-or filet-or--centre">
    </div>
    <div class="bloc-texte reveler">
      <?php foreach ($regles['paragraphes'] as $p): ?>
        <p><?= e($p) ?></p>
      <?php endforeach; ?>
    </div>
    <div class="encadre reveler">
      <h3><?= e($regles['attention']['titre']) ?></h3>
      <p><?= e($regles['attention']['texte']) ?></p>
    </div>
    <div class="centre reveler" style="margin-top:2.8rem;">
      <a class="btn btn--contour" href="<?= route('reglement') ?>">Règlement général</a>
    </div>
  </div>
</section>

<?= $view->partial('bande-cta') ?>
