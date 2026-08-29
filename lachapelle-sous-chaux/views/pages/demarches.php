<?php
/**
 * Liste des démarches administratives, groupées par famille et filtrables.
 *
 * Le filtre n'est pas décoratif : douze fiches se parcourent mal, et l'usager
 * arrive avec une idée précise — « ma carte d'identité », « déclarer mes
 * travaux ». Il est écrit en liens réels vers les ancres, de sorte que la page
 * reste utilisable sans JavaScript.
 *
 * @var array $page
 * @var array $items
 * @var App\Core\View $view
 */
$hero = ($page['hero'] ?? []) + ['titre' => $page['titre'] ?? '', 'image' => ''];

$familles = [];
foreach ($items as $item) {
    $familles[(string) ($item['famille'] ?? 'autres')][] = $item;
}
$intitules = (array) ($page['familles'] ?? []);
?>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<?php if (!empty($page['sous_titre'])): ?>
<section class="section section--chapo">
  <div class="conteneur conteneur--etroit">
    <p class="chapo reveler"><?= e($page['sous_titre']) ?></p>
  </div>
</section>
<?php endif; ?>

<?php if (count($familles) > 1): ?>
<nav class="sommaire" aria-label="<?= e(t('Familles de démarches')) ?>">
  <div class="conteneur">
    <ul class="sommaire__liste">
      <?php foreach ($familles as $cle => $liste): ?>
        <li><a class="sommaire__lien" href="#<?= e($cle) ?>">
          <?= e($intitules[$cle]['titre'] ?? ucfirst($cle)) ?>
          <span class="sommaire__compte"><?= count($liste) ?></span>
        </a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</nav>
<?php endif; ?>

<?php $rang = 0; foreach ($familles as $cle => $liste): $rang++; ?>
<section class="section<?= $rang % 2 === 0 ? ' section--teinte' : '' ?>" id="<?= e($cle) ?>">
  <div class="conteneur">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e(t('Démarches')) ?></p>
      <h2 class="titre-section"><?= e($intitules[$cle]['titre'] ?? ucfirst($cle)) ?></h2>
      <?php if (!empty($intitules[$cle]['texte'])): ?>
        <p class="section__chapo"><?= e($intitules[$cle]['texte']) ?></p>
      <?php endif; ?>
    </div>

    <ul class="cartes cartes--rubriques">
      <?php foreach ($liste as $item): ?>
        <li class="carte-rubrique reveler">
          <a href="<?= route('demarches', $item['slug']) ?>">
            <span class="carte-rubrique__icone" aria-hidden="true">
              <?= $view->partial('icones', ['nom' => $item['icone'] ?? 'document']) ?>
            </span>
            <h3 class="carte-rubrique__titre"><?= e($item['nom'] ?? '') ?></h3>
            <p class="carte-rubrique__texte"><?= e($item['resume'] ?? '') ?></p>
            <span class="carte-rubrique__lien lien-fleche"><?= e(t('Voir la démarche')) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endforeach; ?>

<?= $view->partial('sections', ['sections' => $page['sections'] ?? [], 'depart' => $rang % 2 === 0 ? 'teinte' : 'blanc']) ?>

<?= $view->partial('bande-cta') ?>
