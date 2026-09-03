<?php
/**
 * Les services de l'État : qui fait quoi, et où s'adresser.
 *
 * Une mairie de village est le premier guichet, mais rarement le bon : la
 * préfecture, la DDT, l'ARS traitent l'essentiel des dossiers. Cette page
 * évite un déplacement inutile en disant, service par service, ce dont il
 * s'occupe.
 *
 * @var array $page
 * @var array $items
 * @var App\Core\View $view
 */
$hero = ($page['hero'] ?? []) + ['titre' => $page['titre'] ?? '', 'image' => ''];
?>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<?php if (!empty($page['sous_titre'])): ?>
<section class="section section--chapo">
  <div class="conteneur conteneur--etroit">
    <p class="chapo reveler"><?= e($page['sous_titre']) ?></p>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="conteneur">
    <ul class="fiches-contact">
      <?php foreach ($items as $service): ?>
        <li class="fiche-contact reveler" id="<?= e($service['slug'] ?? '') ?>">
          <?php if (!empty($service['sigle'])): ?>
            <p class="surtitre"><?= e($service['sigle']) ?></p>
          <?php endif; ?>
          <h2 class="fiche-contact__nom"><?= e($service['nom'] ?? '') ?></h2>
          <?php if (!empty($service['texte'])): ?>
            <p class="fiche-contact__texte"><?= e($service['texte']) ?></p>
          <?php endif; ?>
          <?php if (!empty($service['missions'])): ?>
            <ul class="liste-cochee liste-cochee--serree">
              <?php foreach ($service['missions'] as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if (!empty($service['adresse'])): ?>
            <address class="fiche-contact__adresse"><?= nl2br(e($service['adresse'])) ?></address>
          <?php endif; ?>
          <ul class="fiche-contact__liens">
            <?php if (!empty($service['tel'])): ?>
              <li><a href="<?= e(tel_lien($service['tel'])) ?>">
                <span aria-hidden="true"><?= $view->partial('icones', ['nom' => 'telephone']) ?></span><?= e($service['tel']) ?>
              </a></li>
            <?php endif; ?>
            <?php if (!empty($service['email'])): ?>
              <li><a href="mailto:<?= e($service['email']) ?>">
                <span aria-hidden="true"><?= $view->partial('icones', ['nom' => 'courriel']) ?></span><?= e($service['email']) ?>
              </a></li>
            <?php endif; ?>
            <?php if (!empty($service['site'])): ?>
              <li><a href="<?= e($service['site']) ?>" target="_blank" rel="noopener">
                <span aria-hidden="true"><?= $view->partial('icones', ['nom' => 'lien-externe']) ?></span><?= e(t('Site internet')) ?>
                <span class="sr-only"> — <?= e($service['nom'] ?? '') ?>, <?= e(t('ouvre un nouvel onglet')) ?></span>
              </a></li>
            <?php endif; ?>
          </ul>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<?= $view->partial('sections', ['sections' => $page['sections'] ?? [], 'depart' => 'blanc']) ?>

<?= $view->partial('bande-cta') ?>
