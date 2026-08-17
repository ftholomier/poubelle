<?php
/**
 * En-tête du site, en deux dispositions au choix du client (Paramètres →
 * Apparence) :
 *
 *  - « lateral »    : burger à gauche, logo au centre, panneau qui glisse
 *                     depuis la gauche ;
 *  - « horizontal » : logo à gauche, barre de navigation déroulante au
 *                     centre, actions à droite.
 *
 * Le panneau latéral reste présent dans les deux cas : en disposition
 * horizontale il devient le menu du téléphone et de la tablette, où une
 * barre de navigation ne tient pas. Les deux dispositions partagent donc le
 * même balisage, seul l'affichage change — c'est ce qui permet de basculer
 * de l'une à l'autre sans rien casser.
 *
 * @var array $site
 * @var App\Core\View $view
 * @var string $menuStyle
 */
$chemin = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$resa = $site['reservation'];
$horizontal = ($menuStyle ?? 'lateral') === 'horizontal';

/** Une entrée de menu est-elle celle de la page affichée ? */
$estActif = static function (array $item) use ($chemin): bool {
    $url = rtrim((string) ($item['url'] ?? ''), '/');
    if ($url === '') {
        return $chemin === '/';
    }
    return $chemin === $url || str_starts_with($chemin, $url . '/');
};
?>
<header class="entete <?= $horizontal ? 'entete--horizontal' : 'entete--lateral' ?>">
  <div class="entete__barre">
    <button class="burger" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="panneau-nav">
      <span></span><span></span><span></span>
    </button>

    <?php /* Deux fichiers superposés plutôt qu'un filtre : au-dessus de la
             photo du bandeau, le mot-symbole magenta du logo tombe à 3:1 et
             devient illisible ; c'est la variante claire qui s'affiche là, et
             la version d'origine une fois la barre devenue blanche. */ ?>
    <a class="entete__logo" href="<?= route('accueil') ?>" aria-label="<?= e($site['nom']) ?> — Accueil">
      <img class="entete__logo-img entete__logo-img--clair"
           src="<?= asset($site['logo']['clair'] ?? 'assets/img/logo/logo-villard-clair.png') ?>"
           alt="<?= e($site['nom']) ?> — <?= e($site['baseline']) ?>" width="180" height="225">
      <img class="entete__logo-img entete__logo-img--sombre"
           src="<?= asset($site['logo']['principal'] ?? 'assets/img/logo/logo-villard.png') ?>"
           alt="" aria-hidden="true" width="180" height="225">
    </a>

<?php if ($horizontal): ?>
    <nav class="navbar" aria-label="Navigation principale">
      <ul class="navbar__liste">
        <?php foreach ($site['menu'] as $item):
            $actif = $estActif($item);
            $sous  = $item['sous_menu'] ?? [];
        ?>
          <li class="navbar__item<?= $sous !== [] ? ' navbar__item--parent' : '' ?>">
            <a class="navbar__lien" href="<?= lien($item['url']) ?>"<?= $actif ? ' aria-current="page"' : '' ?>>
              <?= e($item['libelle']) ?>
            </a>
            <?php if ($sous !== []): ?>
              <div class="navbar__sous">
                <?php foreach ($sous as $lien): ?>
                  <a href="<?= lien($lien['url']) ?>"><?= e($lien['libelle']) ?></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
<?php endif; ?>

    <div class="entete__droite">
      <?= $view->partial('langues', ['variante' => 'entete', 'cle' => $cle, 'fiche' => $fiche]) ?>
      <?php if (($site['contact']['telephone'] ?? '') !== ''): ?>
      <a class="entete__tel" href="<?= e(tel_lien($site['contact']['telephone'])) ?>">
        <?= e($site['contact']['telephone']) ?>
      </a>
      <?php endif; ?>
      <?php if (($resa['principal']['url'] ?? '') !== ''): ?>
      <a class="btn btn--magenta entete__cta" href="<?= lien($resa['principal']['url']) ?>">
        <?= e($resa['principal']['libelle']) ?>
      </a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="voile" aria-hidden="true"></div>

<nav id="panneau-nav" class="panneau" aria-label="Navigation principale">
  <div class="panneau__tete">
    <a class="panneau__logo" href="<?= route('accueil') ?>" aria-label="<?= e($site['nom']) ?> — Accueil">
      <img src="<?= asset($site['logo']['clair'] ?? 'assets/img/logo/logo-villard-clair.png') ?>"
           alt="<?= e($site['nom']) ?> — <?= e($site['baseline']) ?>">
    </a>
    <button class="panneau__fermer"><?= e(t('Fermer')) ?></button>
  </div>

  <div class="panneau__corps">
    <?php foreach ($site['menu'] as $i => $item):
        $num = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
        $actif = $estActif($item);
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
    <?php if (($resa['principal']['url'] ?? '') !== ''): ?>
      <a class="btn btn--magenta" href="<?= lien($resa['principal']['url']) ?>"><?= e($resa['principal']['libelle']) ?></a>
    <?php endif; ?>
    <?php if (($resa['secondaire']['url'] ?? '') !== ''): ?>
      <a class="btn btn--contour" href="<?= lien($resa['secondaire']['url']) ?>"><?= e($resa['secondaire']['libelle']) ?></a>
    <?php endif; ?>
    <?= $view->partial('langues', ['variante' => 'panneau', 'cle' => $cle, 'fiche' => $fiche]) ?>
    <p class="panneau__coordonnees">
      <?= e($site['adresse']['rue']) ?>, <?= e($site['adresse']['cp']) ?> <?= e($site['adresse']['ville']) ?><br>
      <?php if (($site['contact']['telephone'] ?? '') !== ''): ?>
        <a href="<?= e(tel_lien($site['contact']['telephone'])) ?>"><?= e($site['contact']['telephone']) ?></a>
      <?php endif; ?>
    </p>
  </div>
</nav>
