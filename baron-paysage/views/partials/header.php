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

    <?php /* Le logo horizontal porte déjà le nom et le métier : le composer
             une seconde fois en texte à côté ferait doublon. Les deux
             versions du logo sont posées ensemble et permutées en CSS —
             l'une tient sur la photo du bandeau, l'autre sur le fond crème
             de la barre réduite. Un échange en JavaScript aurait fait
             clignoter le logo à chaque défilement. */ ?>
    <a class="entete__logo" href="<?= route('accueil') ?>" aria-label="<?= e($site['nom']) ?> — <?= e(t('Accueil')) ?>">
      <img class="entete__marque entete__marque--clair"
           src="<?= asset($site['logo']['clair'] ?? 'assets/img/logo/logo-baron-clair.svg') ?>"
           alt="" width="1330" height="329" aria-hidden="true">
      <img class="entete__marque entete__marque--sombre"
           src="<?= asset($site['logo']['horizontal'] ?? 'assets/img/logo/logo-baron.svg') ?>"
           alt="" width="1330" height="329" aria-hidden="true">
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
              <?php /* La feuille marque l'entrée courante. Le client l'avait
                       relevée sur l'ancien site : elle y était retournée et
                       d'un vert qui n'était pas celui de la marque. Elle est
                       redessinée ici, orientée comme celle du logo — pointe
                       en haut à droite — et peinte au vert de la charte. */ ?>
              <span class="navbar__feuille" aria-hidden="true">
                <?= $view->partial('icones', ['nom' => 'feuille']) ?>
              </span>
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
      <a class="btn btn--vert entete__cta" href="<?= lien($resa['principal']['url']) ?>">
        <?= e($resa['principal']['libelle']) ?>
      </a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="voile" aria-hidden="true"></div>

<nav id="panneau-nav" class="panneau" aria-label="Navigation principale">
  <div class="panneau__tete">
    <?php /* Picto seul : dans le panneau, le nom du site est déjà porté par
             le titre du document et par le lien d'accueil. */ ?>
    <a class="panneau__logo" href="<?= route('accueil') ?>" aria-label="<?= e($site['nom']) ?> — Accueil">
      <?php /* Version claire de l'emblème : le panneau est sur l'ardoise, et
               la feuille du logo est un évidement — en version sombre, elle
               emprunterait la couleur du panneau et disparaîtrait. */ ?>
      <img class="panneau__embleme"
           src="<?= asset('assets/img/logo/embleme-baron-clair.svg') ?>"
           alt="" width="505" height="443">
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
      <a class="btn btn--vert" href="<?= lien($resa['principal']['url']) ?>"><?= e($resa['principal']['libelle']) ?></a>
    <?php endif; ?>
    <?php if (($resa['secondaire']['url'] ?? '') !== ''): ?>
      <a class="btn btn--contour" href="<?= lien($resa['secondaire']['url']) ?>"><?= e($resa['secondaire']['libelle']) ?></a>
    <?php endif; ?>
    <?= $view->partial('langues', ['variante' => 'panneau', 'cle' => $cle, 'fiche' => $fiche]) ?>
    <p class="panneau__coordonnees">
      <?php $lignes = array_filter([
          trim((string) ($site['adresse']['rue'] ?? '')),
          trim(($site['adresse']['cp'] ?? '') . ' ' . ($site['adresse']['ville'] ?? '')),
      ], static fn(string $l): bool => $l !== ''); ?>
      <?= e(implode(', ', $lignes)) ?><br>
      <?php if (($site['contact']['telephone'] ?? '') !== ''): ?>
        <a href="<?= e(tel_lien($site['contact']['telephone'])) ?>"><?= e($site['contact']['telephone']) ?></a>
      <?php endif; ?>
    </p>
  </div>
</nav>
