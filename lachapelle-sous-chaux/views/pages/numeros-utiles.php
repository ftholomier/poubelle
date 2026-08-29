<?php
/**
 * Numéros utiles.
 *
 * Les urgences d'abord, en grand, sur fond sombre : c'est la seule page du
 * site qu'on ouvre en situation de panique, et elle doit se lire à bout de
 * bras. Le reste suit en rubriques.
 *
 * @var array $page
 * @var array $rubriques
 * @var App\Core\View $view
 */
$hero = ($page['hero'] ?? []) + ['titre' => $page['titre'] ?? '', 'image' => ''];

$urgences = array_values(array_filter($rubriques, static fn(array $r): bool => !empty($r['urgence'])));
$autres   = array_values(array_filter($rubriques, static fn(array $r): bool => empty($r['urgence'])));
?>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<?php foreach ($urgences as $rubrique): ?>
<section class="section section--sombre" id="<?= e($rubrique['slug'] ?? 'urgences') ?>">
  <div class="conteneur">
    <div class="section__tete section__tete--centre reveler">
      <p class="surtitre surtitre--clair"><?= e(t('En cas d’urgence')) ?></p>
      <h2 class="titre-section"><?= e($rubrique['nom'] ?? '') ?></h2>
      <?php if (!empty($rubrique['texte'])): ?>
        <p class="section__chapo"><?= e($rubrique['texte']) ?></p>
      <?php endif; ?>
    </div>
    <ul class="urgences">
      <?php foreach ($rubrique['numeros'] ?? [] as $n): ?>
        <li class="urgence reveler">
          <a href="<?= e(tel_lien($n['numero'] ?? '')) ?>">
            <span class="urgence__numero"><?= e($n['numero'] ?? '') ?></span>
            <span class="urgence__libelle"><?= e($n['libelle'] ?? '') ?></span>
            <?php if (!empty($n['texte'])): ?>
              <span class="urgence__texte"><?= e($n['texte']) ?></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endforeach; ?>

<?php $rang = 0; foreach ($autres as $rubrique): $rang++; ?>
<section class="section<?= $rang % 2 === 0 ? ' section--teinte' : '' ?>" id="<?= e($rubrique['slug'] ?? '') ?>">
  <div class="conteneur">
    <div class="section__tete reveler">
      <h2 class="titre-section"><?= e($rubrique['nom'] ?? '') ?></h2>
      <?php if (!empty($rubrique['texte'])): ?>
        <p class="section__chapo"><?= e($rubrique['texte']) ?></p>
      <?php endif; ?>
    </div>
    <ul class="numeros">
      <?php foreach ($rubrique['numeros'] ?? [] as $n): ?>
        <li class="numero reveler">
          <div class="numero__corps">
            <p class="numero__libelle"><?= e($n['libelle'] ?? '') ?></p>
            <?php if (!empty($n['texte'])): ?><p class="numero__texte"><?= e($n['texte']) ?></p><?php endif; ?>
            <?php if (!empty($n['adresse'])): ?><address class="numero__adresse"><?= nl2br(e($n['adresse'])) ?></address><?php endif; ?>
          </div>
          <?php if (!empty($n['numero'])): ?>
            <a class="numero__tel" href="<?= e(tel_lien($n['numero'])) ?>">
              <span aria-hidden="true"><?= $view->partial('icones', ['nom' => 'telephone']) ?></span>
              <?= e($n['numero']) ?>
            </a>
          <?php endif; ?>
          <?php if (!empty($n['site'])): ?>
            <a class="numero__site" href="<?= e($n['site']) ?>" target="_blank" rel="noopener">
              <span aria-hidden="true"><?= $view->partial('icones', ['nom' => 'lien-externe']) ?></span>
              <?= e(t('Site')) ?><span class="sr-only"> — <?= e($n['libelle'] ?? '') ?>, <?= e(t('ouvre un nouvel onglet')) ?></span>
            </a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endforeach; ?>

<?= $view->partial('sections', ['sections' => $page['sections'] ?? [], 'depart' => $rang % 2 === 0 ? 'teinte' : 'blanc']) ?>

<?= $view->partial('bande-cta') ?>
