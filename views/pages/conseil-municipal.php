<?php
/**
 * Le conseil municipal : le maire, les adjoints, les conseillers délégués et
 * les conseillers.
 *
 * Le trombinoscope est hiérarchisé plutôt qu'alphabétique. Un administré qui
 * cherche « qui s'occupe des travaux » ne connaît pas le nom : il lit les
 * délégations. C'est donc la délégation qui porte la carte, et le nom qui la
 * signe.
 *
 * @var array $page
 * @var array $conseil
 * @var App\Core\View $view
 */
$hero = ($page['hero'] ?? []) + ['titre' => $page['titre'] ?? '', 'image' => ''];
$groupes = (array) ($conseil['groupes'] ?? []);
?>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<?php if (!empty($page['sous_titre'])): ?>
<section class="section section--chapo">
  <div class="conteneur conteneur--etroit">
    <p class="chapo reveler"><?= e($page['sous_titre']) ?></p>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($conseil['photo'])): ?>
<section class="section">
  <div class="conteneur">
    <figure class="figure-large reveler">
      <?= balise_image($conseil['photo'], (string) ($conseil['photo_alt'] ?? '')) ?>
      <?php if (!empty($conseil['photo_legende'])): ?>
        <figcaption class="figure-large__legende"><?= e($conseil['photo_legende']) ?></figcaption>
      <?php endif; ?>
    </figure>
  </div>
</section>
<?php endif; ?>

<?php foreach ($groupes as $rang => $groupe): ?>
  <?php $sombre = ($groupe['fond'] ?? '') === 'sombre'; ?>
  <section class="section<?= $sombre ? ' section--sombre' : ($rang % 2 === 0 ? ' section--teinte' : '') ?>">
    <div class="conteneur">
      <div class="section__tete reveler">
        <?php if (!empty($groupe['surtitre'])): ?>
          <p class="surtitre<?= $sombre ? ' surtitre--clair' : '' ?>"><?= e($groupe['surtitre']) ?></p>
        <?php endif; ?>
        <h2 class="titre-section"><?= e($groupe['titre'] ?? '') ?></h2>
        <?php if (!empty($groupe['texte'])): ?>
          <p class="section__chapo"><?= e($groupe['texte']) ?></p>
        <?php endif; ?>
      </div>

      <?php $nbMembres = count($groupe['membres'] ?? []); ?>
      <ul class="elus<?= $nbMembres > 6 ? ' elus--compact' : '' ?><?= $nbMembres === 1 ? ' elus--seul' : '' ?>">
        <?php foreach ($groupe['membres'] ?? [] as $membre): ?>
          <li class="elu reveler">
            <?php if (!empty($membre['fonction'])): ?>
              <p class="elu__fonction"><?= e($membre['fonction']) ?></p>
            <?php endif; ?>
            <h3 class="elu__nom"><?= e($membre['nom'] ?? '') ?></h3>
            <?php if (!empty($membre['delegation'])): ?>
              <p class="elu__delegation"><?= e($membre['delegation']) ?></p>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
<?php endforeach; ?>

<?= $view->partial('sections', ['sections' => $page['sections'] ?? [], 'depart' => (count($groupes) % 2 === 0) ? 'blanc' : 'teinte']) ?>

<?= $view->partial('bande-cta') ?>
