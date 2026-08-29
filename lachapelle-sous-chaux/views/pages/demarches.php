<?php
/**
 * Liste des démarches administratives, filtrable par famille.
 *
 * Le filtre remplace un sommaire qui pointait vers des ancres. Un lien d'ancre
 * paraît filtrer et ne fait que descendre : les autres familles restent sous
 * les yeux, on perd la position de la page, et le bouton Précédent du
 * navigateur devient inutilisable — chaque clic laissait une entrée dans
 * l'historique. Ici, choisir une famille ne montre plus qu'elle.
 *
 * Le filtre passe par l'adresse — /demarches?famille=urbanisme — et non par le
 * seul JavaScript. La sélection est donc partageable, s'ajoute aux favoris,
 * survit à un rechargement, et la page reste utilisable sans script : c'est le
 * serveur qui masque alors les fiches hors filtre. Le JavaScript n'ajoute que
 * l'absence d'aller-retour.
 *
 * Toutes les fiches restent dans le HTML, masquées et non l'inverse : un
 * moteur de recherche voit la page entière quelle que soit la famille choisie,
 * et l'adresse canonique reste /demarches.
 *
 * @var array  $page
 * @var array  $items
 * @var string $famille   famille retenue, ou '' pour toutes
 * @var App\Core\View $view
 */
$hero = ($page['hero'] ?? []) + ['titre' => $page['titre'] ?? '', 'image' => ''];

$comptes = [];
foreach ($items as $item) {
    $cle = (string) ($item['famille'] ?? 'autres');
    $comptes[$cle] = ($comptes[$cle] ?? 0) + 1;
}
$intitules = (array) ($page['familles'] ?? []);
$famille   = (string) ($famille ?? '');

/** Intitulé d'une famille, avec un repli lisible si le contenu n'en donne pas. */
$nomFamille = static fn(string $cle): string
    => (string) ($intitules[$cle]['titre'] ?? ucfirst(str_replace('-', ' ', $cle)));
?>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<?php if (!empty($page['sous_titre'])): ?>
<section class="section section--chapo">
  <div class="conteneur conteneur--etroit">
    <p class="chapo reveler"><?= e($page['sous_titre']) ?></p>
  </div>
</section>
<?php endif; ?>

<?php if (count($comptes) > 1): ?>
<?php /* Des liens, pas des boutons : sans JavaScript ils mènent à la même page
         filtrée par le serveur. aria-current="page" dit lequel est en cours —
         c'est la seule façon pour un lecteur d'écran de savoir ce qu'il
         regarde, la couleur ne lui parvenant pas. */ ?>
<nav class="filtres" aria-label="<?= e(t('Familles de démarches')) ?>" data-filtres>
  <div class="conteneur">
    <ul class="filtres__liste">
      <li>
        <a class="filtres__lien" href="<?= route('demarches') ?>" data-filtre=""
           <?= $famille === '' ? 'aria-current="page"' : '' ?>>
          <?= e(t('Toutes')) ?>
          <span class="filtres__compte"><?= count($items) ?></span>
        </a>
      </li>
      <?php foreach ($comptes as $cle => $combien): ?>
        <li>
          <?php /* Le titre et l'introduction de la famille voyagent avec le
                   lien : le script les repose au-dessus de la grille sans
                   redemander la page, et le serveur reste seul à décider de
                   leur contenu. */ ?>
          <a class="filtres__lien" href="<?= route('demarches') ?>?famille=<?= e(rawurlencode($cle)) ?>"
             data-filtre="<?= e($cle) ?>"
             data-titre="<?= e($nomFamille($cle)) ?>"
             data-intro="<?= e((string) ($intitules[$cle]['texte'] ?? '')) ?>"
             <?= $famille === $cle ? 'aria-current="page"' : '' ?>>
            <?= e($nomFamille($cle)) ?>
            <span class="filtres__compte"><?= $combien ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</nav>
<?php endif; ?>

<section class="section">
  <div class="conteneur">
    <?php /* Le titre suit le filtre : il dit ce qu'on regarde, et c'est lui
             que le script met à jour. Sans famille retenue, l'intitulé
             général ; avec, celui de la famille et son texte d'introduction
             s'il y en a un. */ ?>
    <div class="section__tete reveler">
      <p class="surtitre"><?= e(t('Démarches')) ?></p>
      <?php
      /* Le chapô de la page, au-dessus du filtre, dit déjà ce qu'est la page :
         l'état « Toutes » n'a donc pas de texte à lui, et le paragraphe se
         retire plutôt que de rester vide. Le script fait de même. */
      $titreCourant = $famille === '' ? t('Toutes les démarches') : $nomFamille($famille);
      $texteCourant = $famille === '' ? '' : (string) ($intitules[$famille]['texte'] ?? '');
      ?>
      <h2 class="titre-section" data-filtre-titre
          data-titre-tout="<?= e(t('Toutes les démarches')) ?>"><?= e($titreCourant) ?></h2>
      <p class="section__chapo" data-filtre-texte<?= $texteCourant === '' ? ' hidden' : '' ?>><?= e($texteCourant) ?></p>
    </div>

    <ul class="cartes cartes--rubriques" data-filtre-liste>
      <?php foreach ($items as $item): ?>
        <?php $cle = (string) ($item['famille'] ?? 'autres'); ?>
        <li class="carte-rubrique reveler" data-famille="<?= e($cle) ?>"
            <?= $famille !== '' && $famille !== $cle ? 'hidden' : '' ?>>
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

    <?php /* aria-live : le nombre de fiches change sans que la page bouge, et
             rien ne le dirait à qui ne voit pas l'écran. */ ?>
    <p class="filtres__resultat sr-only" data-filtre-annonce role="status" aria-live="polite"></p>
  </div>
</section>

<?= $view->partial('sections', ['sections' => $page['sections'] ?? [], 'depart' => 'blanc']) ?>

<?= $view->partial('bande-cta') ?>
