<?php
/**
 * En-tête du site, en deux dispositions au choix de la mairie (Paramètres →
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
// « appel » depuis la correction du nommage ; « reservation » venait du site
// commercial dont le socle est tiré. Les deux se lisent, le temps que les
// sites déjà en ligne soient réenregistrés une fois.
$resa = $site['appel'] ?? $site['reservation'] ?? ['principal' => ['libelle' => '', 'url' => '/contact']];
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
           src="<?= asset($site['logo']['clair'] ?? 'assets/img/logo/logo-angeot-clair.svg') ?>"
           alt="" width="711" height="232" aria-hidden="true">
      <img class="entete__marque entete__marque--sombre"
           src="<?= asset($site['logo']['horizontal'] ?? 'assets/img/logo/logo-angeot.svg') ?>"
           alt="" width="711" height="232" aria-hidden="true">
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
              <?php /* Le clocher marque l'entrée courante : c'est la
                       silhouette que porte le logo de la commune, et le seul
                       signe que tout le village reconnaît. Peint au bleu de
                       la charte. */ ?>
              <span class="navbar__feuille" aria-hidden="true">
                <?= $view->partial('icones', ['nom' => 'clocher']) ?>
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
      <a class="btn btn--bleu entete__cta" href="<?= lien($resa['principal']['url']) ?>">
        <?= e($resa['principal']['libelle']) ?>
      </a>
      <?php endif; ?>
    </div>

    <?php /* Accès permanent au contenu vivant, à toutes les largeurs et dans
             les deux états de la barre. C'est le seul lien du site qui ne
             disparaît jamais : la navigation se replie derrière le burger sous
             1080 px, et le numéro comme le bouton d'appel s'effacent sous
             780 px — l'actualité de la commune, elle, reste atteignable en un
             geste depuis n'importe quelle page, y compris une fiche de démarche
             trouvée par un moteur de recherche.

             Un pictogramme et non un bouton libellé : la barre porte déjà sept
             rubriques, un numéro et un appel à l'action, et un neuvième bloc de
             texte y faisait passer les libellés du menu sur deux lignes. Le
             nom reste écrit, pour les lecteurs d'écran et pour l'infobulle —
             une icône seule sans nom accessible n'est pas un lien.

             Il est posé hors de .entete__droite précisément pour cela : ce
             bloc-là est masqué sur téléphone, lui ne l'est jamais. */ ?>
    <?php
    /* La pastille compte ce que la mairie a publié depuis la dernière visite.
       Le serveur ne connaît pas cette date — il ne dépose rien et ne suit
       personne — il rend donc les dates de publication et un repli : le nombre
       de parutions du dernier mois, qui est ce que voit un visiteur sans
       JavaScript comme au tout premier passage. Le navigateur affine ensuite
       avec ce que lui seul sait.

       Les dates sont plafonnées : au-delà d'une trentaine, le compteur
       s'écrirait « 30+ » de toute façon, et il n'y a pas de raison d'alourdir
       chaque page de l'historique complet de la commune. */
    $nouveautes = isset($vivant) ? $vivant->nouveautes() : 0;
    $dates      = isset($vivant) ? array_slice($vivant->datesPubliees(), 0, 30) : [];
    ?>
    <a class="entete__actu<?= $nouveautes > 0 ? ' entete__actu--neuf' : '' ?>"
       href="<?= route('actualites') ?>"
       data-actu-dates="<?= e(implode(',', $dates)) ?>">
      <span class="entete__actu-icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'cloche']) ?></span>
      <?php /* « Actus & agenda » et pas « Actualités » : le lien mène aux
               trois rubriques du contenu vivant, et c'est le mot « agenda »
               qui le dit — sans lui, on croit n'y trouver que des articles.
               Il est affiché à toutes les largeurs : un pictogramme, si net
               soit-il, ne remplace pas le mot. */ ?>
      <span class="entete__actu-texte"><?= e(t('Actus & agenda')) ?></span>
      <?php /* La pastille est décorative pour les lecteurs d'écran : le nom du
               lien, juste en dessous, porte déjà le compte en toutes lettres,
               et l'annoncer deux fois ferait « Actualités 2 Actualités, agenda
               et Flash Info, 2 nouveautés ». */ ?>
      <span class="entete__actu-pastille" data-actu-pastille aria-hidden="true"
            <?= $nouveautes > 0 ? '' : 'hidden' ?>><?= (int) $nouveautes ?></span>
      <span class="sr-only" data-actu-nom><?= e(t('Actualités, agenda et Flash Info')) ?><?php
        if ($nouveautes > 0): ?> — <?= e(sprintf(
          $nouveautes > 1 ? t('%d nouveautés') : t('%d nouveauté'), $nouveautes
        )) ?><?php endif; ?></span>
    </a>
  </div>

  <?php /* Le faisceau qui ferme la barre : le trait au repos, et dedans les
           deux lobes de l'onde qui l'allume. Chacun a besoin de son propre
           élément — deux pseudo-éléments ne suffisaient plus dès lors que
           l'onde s'écarte des deux côtés à la fois. Décoratif de bout en
           bout : rien à annoncer aux lecteurs d'écran. */ ?>
  <span class="entete__faisceau" aria-hidden="true"><i class="entete__onde"></i></span>
</header>

<div class="voile" aria-hidden="true"></div>

<nav id="panneau-nav" class="panneau" aria-label="Navigation principale">
  <div class="panneau__tete">
    <a class="panneau__logo" href="<?= route('accueil') ?>" aria-label="<?= e($site['nom']) ?> — Accueil">
      <?php /* Le logo complet, pas l'emblème seul. L'emblème n'est que les deux
               arcs : sur l'ardoise du panneau il se réduit à un anneau qu'on
               devine, et il ne dit pas de quelle commune il s'agit. Le logo
               pour fond sombre, lui, porte le nom écrit en blanc — c'est ce
               nom qui rend l'en-tête du panneau lisible d'un coup d'œil, et
               c'est le même fichier que le pied de page. */ ?>
      <img class="panneau__embleme"
           src="<?= asset($site['logo']['clair'] ?? 'assets/img/logo/logo-angeot-clair.svg') ?>"
           alt="" width="711" height="232">
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
      <a class="btn btn--bleu" href="<?= lien($resa['principal']['url']) ?>"><?= e($resa['principal']['libelle']) ?></a>
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
