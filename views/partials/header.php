<?php
/**
 * En-tête fixe : burger à gauche, logo au centre, réservation à droite.
 * Le panneau de navigation glisse depuis la gauche.
 *
 * @var array $site
 */
$chemin = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$resa = $site['reservation'];
?>
<header class="entete">
  <div class="entete__barre">
    <button class="burger" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="panneau-nav">
      <span></span><span></span><span></span>
    </button>

    <a class="entete__logo" href="<?= route('accueil') ?>" aria-label="<?= e($site['nom']) ?> — Accueil">
      <img src="<?= asset('assets/img/logo/logo-etang-fourchu.svg') ?>"
           alt="<?= e($site['nom']) ?> — <?= e($site['baseline']) ?>" width="180" height="152">
    </a>

    <div class="entete__droite">
      <?= $view->partial('langues', ['variante' => 'entete', 'cle' => $cle, 'fiche' => $fiche]) ?>
      <a class="entete__tel" href="tel:+33<?= e(substr(preg_replace('/\D+/', '', $site['contact']['telephone']), 1)) ?>">
        <?= e($site['contact']['telephone']) ?>
      </a>
      <div class="resa">
        <button class="resa__btn btn btn--or" aria-expanded="false" aria-haspopup="true"><?= e(t('Réserver')) ?></button>
        <div class="resa__liste">
          <a href="<?= e($resa['hebergement']['url']) ?>" target="_blank" rel="noopener"><?= e($resa['hebergement']['libelle']) ?></a>
          <a href="<?= e($resa['etang']['url']) ?>" target="_blank" rel="noopener"><?= e($resa['etang']['libelle']) ?></a>
        </div>
      </div>
    </div>
  </div>
</header>

<div class="voile" aria-hidden="true"></div>

<nav id="panneau-nav" class="panneau" aria-label="Navigation principale">
  <div class="panneau__tete">
    <a class="panneau__logo" href="<?= route('accueil') ?>" aria-label="<?= e($site['nom']) ?> — Accueil">
      <img src="<?= asset('assets/img/logo/logo-etang-fourchu-clair.svg') ?>"
           alt="<?= e($site['nom']) ?> — <?= e($site['baseline']) ?>">
    </a>
    <button class="panneau__fermer"><?= e(t('Fermer')) ?></button>
  </div>

  <div class="panneau__corps">
    <?php foreach ($site['menu'] as $i => $item):
        $num = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
        $actif = $chemin === rtrim($item['url'], '/') || ($item['url'] !== '/' && str_starts_with($chemin, rtrim($item['url'], '/')));
    ?>
      <?php if (!empty($item['sous_menu'])): ?>
        <div class="panneau__accordeon"<?= $actif ? ' data-ouvert' : '' ?>>
          <button aria-expanded="<?= $actif ? 'true' : 'false' ?>">
            <span class="panneau__num"><?= $num ?></span><?= e($item['libelle']) ?>
          </button>
          <div class="panneau__sous">
            <a href="<?= lien($item['url']) ?>"><?= e(t('Découvrir')) ?></a>
            <?php foreach ($item['sous_menu'] as $sous): ?>
              <a href="<?= lien($sous['url']) ?>"><?= e($sous['libelle']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else: ?>
        <a class="panneau__lien" href="<?= lien($item['url']) ?>"<?= $actif ? ' aria-current="page"' : '' ?>>
          <span class="panneau__num"><?= $num ?></span><?= e($item['libelle']) ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div class="panneau__pied">
    <a class="btn btn--or" href="<?= e($resa['hebergement']['url']) ?>" target="_blank" rel="noopener"><?= e($resa['hebergement']['libelle']) ?></a>
    <a class="btn btn--contour" href="<?= e($resa['etang']['url']) ?>" target="_blank" rel="noopener"><?= e($resa['etang']['libelle']) ?></a>
    <?= $view->partial('langues', ['variante' => 'panneau', 'cle' => $cle, 'fiche' => $fiche]) ?>
    <p class="panneau__coordonnees">
      <?= e($site['adresse']['rue']) ?>, <?= e($site['adresse']['cp']) ?> <?= e($site['adresse']['ville']) ?><br>
      <a href="tel:+33<?= e(substr(preg_replace('/\D+/', '', $site['contact']['telephone']), 1)) ?>"><?= e($site['contact']['telephone']) ?></a>
    </p>
  </div>
</nav>
