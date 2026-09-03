<?php
/**
 * Les commissions communales et les comités.
 *
 * Une commission est une liste de noms sous un intitulé : la présenter en
 * tableau serait fidèle, mais illisible sur un téléphone. Chaque commission
 * est donc une carte, et les noms y sont posés en colonnes qui se replient.
 *
 * @var array $page
 * @var array $commissions
 * @var array $comites
 * @var App\Core\View $view
 */
$hero = ($page['hero'] ?? []) + ['titre' => $page['titre'] ?? '', 'image' => ''];

$grille = static function (array $liste) use ($view): void {
    foreach ($liste as $groupe) { ?>
      <li class="commission reveler">
        <span class="commission__picto" aria-hidden="true">
          <?= $view->partial('icones', ['nom' => $groupe['icone'] ?? 'conseil']) ?>
        </span>
        <h3 class="commission__titre"><?= e($groupe['nom'] ?? '') ?></h3>
        <?php if (!empty($groupe['role'])): ?>
          <p class="commission__role"><?= e($groupe['role']) ?></p>
        <?php endif; ?>
        <?php foreach ($groupe['colonnes'] ?? [['membres' => $groupe['membres'] ?? []]] as $colonne): ?>
          <?php if (!empty($colonne['titre'])): ?>
            <p class="commission__sous-titre"><?= e($colonne['titre']) ?></p>
          <?php endif; ?>
          <ul class="commission__membres">
            <?php foreach ($colonne['membres'] ?? [] as $membre): ?>
              <li><?= e($membre) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>
      </li>
    <?php }
};
?>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<?php if (!empty($page['sous_titre'])): ?>
<section class="section section--chapo">
  <div class="conteneur conteneur--etroit">
    <p class="chapo reveler"><?= e($page['sous_titre']) ?></p>
  </div>
</section>
<?php endif; ?>

<?php if ($commissions !== []): ?>
<section class="section" id="commissions">
  <div class="conteneur">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e(t('Conseil municipal')) ?></p>
      <h2 class="titre-section"><?= e(t('Les commissions communales')) ?></h2>
      <p class="section__chapo"><?= e(t('Les commissions préparent les décisions du conseil. Elles se réunissent en amont des séances et rendent leurs conclusions au conseil, qui seul délibère.')) ?></p>
    </div>
    <ul class="commissions"><?php $grille($commissions); ?></ul>
  </div>
</section>
<?php endif; ?>

<?php if ($comites !== []): ?>
<section class="section section--teinte" id="comites">
  <div class="conteneur">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e(t('Représentation')) ?></p>
      <h2 class="titre-section"><?= e(t('Comités et syndicats')) ?></h2>
      <p class="section__chapo"><?= e(t('La commune siège dans plusieurs structures intercommunales. Ces élus y portent la voix d’Angeot.')) ?></p>
    </div>
    <ul class="commissions"><?php $grille($comites); ?></ul>
  </div>
</section>
<?php endif; ?>

<?= $view->partial('sections', ['sections' => $page['sections'] ?? [], 'depart' => $comites !== [] ? 'teinte' : 'blanc']) ?>

<?= $view->partial('bande-cta') ?>
