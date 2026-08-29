<?php
/**
 * Page de documents téléchargeables : comptes-rendus du conseil, budgets,
 * bulletins municipaux.
 *
 * Une seule vue pour trois pages : elles ne diffèrent que par le jeu de
 * fichiers listé, et par les blocs de texte que la page porte autour.
 *
 * @var array $page
 * @var array $documents
 * @var App\Core\View $view
 */
$hero = ($page['hero'] ?? []) + ['titre' => $page['titre'] ?? '', 'image' => ''];

// Les documents sont groupés par année : dix ans de comptes-rendus font une
// liste que personne ne parcourt, dix listes de dix se lisent.
$parAnnee = [];
foreach ($documents as $doc) {
    $annee = substr((string) ($doc['date'] ?? ''), 0, 4) ?: '—';
    $parAnnee[$annee][] = $doc;
}
krsort($parAnnee);
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
    <?php if ($parAnnee === []): ?>
      <p class="vide reveler"><?= e(t('Aucun document n’est publié pour le moment. Les documents antérieurs sont consultables en mairie.')) ?></p>
    <?php else: ?>
      <?php foreach ($parAnnee as $annee => $liste): ?>
        <div class="annee reveler">
          <h2 class="annee__titre"><?= e($annee) ?></h2>
          <?= $view->partial('documents', ['documents' => $liste]) ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?= $view->partial('sections', ['sections' => $page['sections'] ?? [], 'depart' => 'blanc']) ?>

<?= $view->partial('bande-cta') ?>
