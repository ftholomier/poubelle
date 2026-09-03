<?php
/**
 * Plan du site.
 *
 * Il se construit depuis le menu et les collections : une page ajoutée à
 * Seo::PAGES et au menu y apparaît sans qu'on y pense. Un plan tenu à la main
 * finit toujours par mentir, et c'est précisément la page qu'on consulte quand
 * on n'a rien trouvé ailleurs.
 *
 * @var array $page
 * @var array $menu
 * @var array $demarches
 * @var array $actualites
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
    <ul class="plan">
      <li class="plan__rubrique reveler">
        <h2 class="plan__titre"><a href="<?= route('accueil') ?>"><?= e(t('Accueil')) ?></a></h2>
      </li>
      <?php foreach ($menu as $entree): ?>
        <li class="plan__rubrique reveler">
          <h2 class="plan__titre"><a href="<?= lien($entree['url']) ?>"><?= e($entree['libelle']) ?></a></h2>
          <?php if (!empty($entree['sous_menu'])): ?>
            <ul class="plan__liens">
              <?php foreach ($entree['sous_menu'] as $l): ?>
                <li><a href="<?= lien($l['url']) ?>"><?= e($l['libelle']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>

      <li class="plan__rubrique reveler">
        <h2 class="plan__titre"><a href="<?= route('demarches') ?>"><?= e(t('Toutes les démarches')) ?></a></h2>
        <ul class="plan__liens">
          <?php foreach ($demarches as $d): ?>
            <li><a href="<?= route('demarches', $d['slug']) ?>"><?= e($d['nom']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </li>

      <?php if ($actualites !== []): ?>
        <li class="plan__rubrique reveler">
          <h2 class="plan__titre"><a href="<?= route('actualites') ?>"><?= e(t('Actualités')) ?></a></h2>
          <ul class="plan__liens">
            <?php foreach ($actualites as $a): ?>
              <li><a href="<?= route('actualites', $a['slug']) ?>"><?= e($a['titre']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </li>
      <?php endif; ?>

      <li class="plan__rubrique reveler">
        <h2 class="plan__titre"><?= e(t('Informations légales')) ?></h2>
        <ul class="plan__liens">
          <li><a href="<?= route('mentions-legales') ?>"><?= e(t('Mentions légales')) ?></a></li>
          <li><a href="<?= route('confidentialite') ?>"><?= e(t('Politique de confidentialité')) ?></a></li>
          <li><a href="<?= route('accessibilite') ?>"><?= e(t('Accessibilité')) ?></a></li>
        </ul>
      </li>
    </ul>
  </div>
</section>

<?= $view->partial('sections', ['sections' => $page['sections'] ?? [], 'depart' => 'blanc']) ?>

<?= $view->partial('bande-cta') ?>
