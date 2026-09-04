<?php
/**
 * Fiche d'une démarche administrative.
 *
 * L'ordre suit celui de la personne au guichet : de quoi il s'agit, où l'on
 * s'adresse, ce qu'il faut apporter, combien de temps ça prend, et le
 * téléservice quand il existe. Les pièces à fournir passent avant le texte
 * d'explication, parce que c'est pour elles qu'on revient sur la page.
 *
 * @var array $page
 * @var array $item
 * @var array $autres  les autres démarches de la même famille
 * @var App\Core\View $view
 */
$hero = ($item['hero'] ?? []) + [
    'titre'    => $item['nom'] ?? '',
    'surtitre' => t('Démarche administrative'),
    'image'    => $item['image'] ?? '',
];
?>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<section class="section">
  <div class="conteneur fiche">
    <div class="fiche__corps">
      <?php if (!empty($item['resume'])): ?>
        <p class="chapo reveler"><?= e($item['resume']) ?></p>
      <?php endif; ?>

      <?php foreach ($item['sections'] ?? [] as $bloc): ?>
        <div class="fiche__bloc reveler">
          <?= $view->partial('bloc', ['bloc' => $bloc]) ?>
        </div>
      <?php endforeach; ?>
    </div>

    <aside class="fiche__cote">
      <?php if (!empty($item['pieces'])): ?>
        <div class="encart reveler">
          <h2 class="encart__titre"><?= e(t('À fournir')) ?></h2>
          <ul class="liste-cochee">
            <?php foreach ($item['pieces'] as $piece): ?><li><?= e($piece) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php $reperes = array_filter([
          t('Où s’adresser')  => $item['guichet'] ?? '',
          t('Délai')          => $item['delai'] ?? '',
          t('Coût')           => $item['cout'] ?? '',
          t('Validité')       => $item['validite'] ?? '',
      ]); ?>
      <?php if ($reperes !== []): ?>
        <div class="encart reveler">
          <h2 class="encart__titre"><?= e(t('En bref')) ?></h2>
          <dl class="encart__reperes">
            <?php foreach ($reperes as $libelle => $valeur): ?>
              <dt><?= e($libelle) ?></dt><dd><?= e($valeur) ?></dd>
            <?php endforeach; ?>
          </dl>
        </div>
      <?php endif; ?>

      <?php if (!empty($item['liens'])): ?>
        <div class="encart encart--bleu reveler">
          <h2 class="encart__titre"><?= e(t('Faire en ligne')) ?></h2>
          <ul class="encart__liens">
            <?php foreach ($item['liens'] as $l): ?>
              <?php $externe = str_starts_with((string) ($l['url'] ?? ''), 'http'); ?>
              <li>
                <a href="<?= $externe ? e($l['url']) : lien($l['url']) ?>"<?= $externe ? ' target="_blank" rel="noopener"' : '' ?>>
                  <?= e($l['libelle'] ?? '') ?>
                  <?php if ($externe): ?>
                    <span aria-hidden="true"><?= $view->partial('icones', ['nom' => 'lien-externe']) ?></span>
                    <span class="sr-only"> — <?= e(t('ouvre un nouvel onglet')) ?></span>
                  <?php endif; ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</section>

<?php if ($autres !== []): ?>
<section class="section section--teinte">
  <div class="conteneur">
    <div class="section__tete reveler">
      <h2 class="titre-section"><?= e(t('Démarches voisines')) ?></h2>
    </div>
    <ul class="cartes cartes--rubriques">
      <?php foreach (array_slice($autres, 0, 3) as $autre): ?>
        <li class="carte-rubrique reveler">
          <a href="<?= route('demarches', $autre['slug']) ?>">
            <span class="carte-rubrique__icone" aria-hidden="true">
              <?= $view->partial('icones', ['nom' => $autre['icone'] ?? 'document']) ?>
            </span>
            <h3 class="carte-rubrique__titre"><?= e($autre['nom'] ?? '') ?></h3>
            <p class="carte-rubrique__texte"><?= e($autre['resume'] ?? '') ?></p>
            <span class="carte-rubrique__lien lien-fleche"><?= e(t('Voir la démarche')) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="section__pied reveler">
      <a class="btn btn--contour" href="<?= route('demarches') ?>"><?= e(t('Toutes les démarches')) ?></a>
    </p>
  </div>
</section>
<?php endif; ?>

<?= $view->partial('bande-cta') ?>
