<?php
/**
 * Page d'accueil.
 *
 * L'ordre des sections suit ce que les gens viennent chercher, dans l'ordre où
 * ils le cherchent : d'abord faire une démarche, ensuite savoir quand la
 * mairie est ouverte, puis lire ce qui se passe au village. La présentation de
 * la commune vient après — elle intéresse le nouvel arrivant, pas l'habitant
 * qui vient renouveler sa carte d'identité.
 *
 * @var array $page
 * @var array $demarches   les démarches les plus demandées
 * @var array $actualites
 * @var array $agenda
 * @var App\Core\Content $content
 * @var App\Core\View $view
 */
$site = $content->load('site');
$hero = $page['hero'];

// le diaporama ne retient que les vues actives ; sans aucune, la photo seule
// du bandeau reste le repli, pour qu'une page d'accueil vidée par erreur ne
// s'affiche jamais sur un fond noir
$vues = array_values(array_filter(
    $hero['diaporama']['vues'] ?? [],
    static fn(array $v): bool => !empty($v['actif']) && ($v['image'] ?? '') !== ''
));
if ($vues === []) {
    $vues = [['image' => $hero['image']]];
}

// Ordre aléatoire, au choix de la mairie. Le tirage est fait ici, à chaque
// affichage : le visiteur qui revient ne retombe pas sur la même première
// image, et le village sous la neige a autant de chances d'ouvrir la page
// qu'un verger en fleurs. L'ordre rangé au back-office n'est pas touché —
// décocher la case le rend tel quel.
if (!empty($hero['diaporama']['aleatoire']) && count($vues) > 1) {
    shuffle($vues);
}
$pause = max(2, (int) ($hero['diaporama']['pause'] ?? 6));
$voile = min(100, max(0, (int) ($hero['voile'] ?? 100)));
?>

<section class="heros heros--accueil"
         style="--pause: <?= $pause ?>s; --voile: <?= number_format($voile / 100, 2, '.', '') ?>">
  <div class="heros__fond heros__fond--diaporama" data-diaporama>
    <?php foreach ($vues as $rang => $vue): ?>
      <div class="heros__photo<?= $rang === 0 ? ' est-visible' : '' ?>" data-vue
           style="background-image:url('<?= image($vue['image']) ?>')"
           aria-hidden="true"></div>
    <?php endforeach; ?>
  </div>

  <div class="heros__contenu conteneur">
    <p class="surtitre surtitre--clair"><?= e($hero['surtitre']) ?></p>
    <h1 class="heros__titre"><?= e($hero['titre']) ?></h1>
    <p class="heros__texte"><?= e($hero['texte']) ?></p>
    <div class="heros__actions">
      <a class="btn btn--vert" href="<?= lien($hero['bouton_principal']['url']) ?>">
        <?= e($hero['bouton_principal']['libelle']) ?>
      </a>
      <a class="btn btn--contour-clair" href="<?= lien($hero['bouton_secondaire']['url']) ?>">
        <?= e($hero['bouton_secondaire']['libelle']) ?>
      </a>
    </div>
  </div>

  <?php if (count($vues) > 1): ?>
    <div class="heros__jauge" data-diaporama-jauge aria-hidden="true">
      <span class="heros__jauge-trait" data-diaporama-trait></span>
    </div>
  <?php endif; ?>
</section>

<?php /* Le bandeau pratique remplace la bande d'indicateurs d'un site
         commercial. Les chiffres de la commune sont plus bas : ce qu'on
         cherche en haut d'un site de mairie, ce sont les horaires du
         guichet. */ ?>
<?php $pratique = (array) ($page['pratique'] ?? []); ?>
<?php if ($pratique !== []): ?>
<section class="bandeau-pratique">
  <div class="conteneur">
    <ul class="bandeau-pratique__liste">
      <?php foreach ($pratique as $item): ?>
        <li class="bandeau-pratique__item reveler">
          <span class="bandeau-pratique__icone" aria-hidden="true">
            <?= $view->partial('icones', ['nom' => $item['icone'] ?? 'horaires']) ?>
          </span>
          <div>
            <p class="bandeau-pratique__label"><?= e($item['libelle'] ?? '') ?></p>
            <?php if (!empty($item['lien'])): ?>
              <a class="bandeau-pratique__valeur" href="<?= str_starts_with((string) $item['lien'], 'tel:') || str_starts_with((string) $item['lien'], 'mailto:') ? e($item['lien']) : lien($item['lien']) ?>">
                <?= e($item['valeur'] ?? '') ?>
              </a>
            <?php else: ?>
              <p class="bandeau-pratique__valeur"><?= e($item['valeur'] ?? '') ?></p>
            <?php endif; ?>
            <?php if (!empty($item['precision'])): ?>
              <p class="bandeau-pratique__precision"><?= e($item['precision']) ?></p>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php /* Les démarches en tête de page : c'est la raison de neuf visites sur
         dix. Six cartes, pas douze — la page complète est à un clic. */ ?>
<?php if ($demarches !== []): ?>
<section class="section" id="demarches">
  <div class="conteneur">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e($page['demarches']['surtitre'] ?? t('Vos démarches')) ?></p>
      <h2 class="titre-section"><?= e($page['demarches']['titre'] ?? '') ?></h2>
      <p class="section__chapo"><?= e($page['demarches']['texte'] ?? '') ?></p>
    </div>

    <ul class="cartes cartes--rubriques">
      <?php foreach ($demarches as $demarche): ?>
        <li class="carte-rubrique reveler">
          <a href="<?= route('demarches', $demarche['slug']) ?>">
            <span class="carte-rubrique__icone" aria-hidden="true">
              <?= $view->partial('icones', ['nom' => $demarche['icone'] ?? 'document']) ?>
            </span>
            <h3 class="carte-rubrique__titre"><?= e($demarche['nom'] ?? '') ?></h3>
            <p class="carte-rubrique__texte"><?= e($demarche['resume'] ?? '') ?></p>
            <span class="carte-rubrique__lien lien-fleche"><?= e(t('Voir la démarche')) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if (!empty($page['demarches']['lien']['url'])): ?>
      <p class="section__pied reveler">
        <a class="btn btn--contour" href="<?= lien($page['demarches']['lien']['url']) ?>">
          <?= e($page['demarches']['lien']['libelle']) ?>
        </a>
      </p>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php /* Actualités et agenda côte à côte : dans un village, ce qui vient de se
         passer et ce qui va se passer se lisent d'un même mouvement. */ ?>
<?php if ($actualites !== [] || $agenda !== []): ?>
<section class="section section--teinte" id="actualites">
  <div class="conteneur">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e($page['vie']['surtitre'] ?? t('La vie du village')) ?></p>
      <h2 class="titre-section"><?= e($page['vie']['titre'] ?? '') ?></h2>
      <p class="section__chapo"><?= e($page['vie']['texte'] ?? '') ?></p>
    </div>

    <div class="vie-village">
      <?php if ($actualites !== []): ?>
        <div class="vie-village__actus">
          <ul class="cartes cartes--actus">
            <?php foreach ($actualites as $actu): ?>
              <li class="carte-actu reveler">
                <a href="<?= route('actualites', $actu['slug']) ?>">
                  <figure class="carte-actu__media">
                    <img src="<?= image($actu['image'] ?? '', true) ?>" alt="<?= e($actu['image_alt'] ?? '') ?>" loading="lazy">
                  </figure>
                  <p class="carte-actu__date"><?= e(date_texte((string) ($actu['date'] ?? ''))) ?></p>
                  <h3 class="carte-actu__titre"><?= e($actu['titre'] ?? '') ?></h3>
                  <p class="carte-actu__texte"><?= e($actu['resume'] ?? '') ?></p>
                  <span class="carte-actu__lien lien-fleche"><?= e(t('Lire la suite')) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
          <p class="section__pied reveler">
            <a class="btn btn--contour" href="<?= route('actualites') ?>"><?= e(t('Toutes les actualités')) ?></a>
          </p>
        </div>
      <?php endif; ?>

      <?php if ($agenda !== []): ?>
        <aside class="vie-village__agenda reveler">
          <h3 class="vie-village__titre"><?= e(t('Prochains rendez-vous')) ?></h3>
          <ul class="agenda-court">
            <?php foreach ($agenda as $e): ?>
              <li class="agenda-court__item">
                <p class="agenda-court__date"><?= e(date_texte((string) ($e['date'] ?? ''), true)) ?></p>
                <p class="agenda-court__titre"><?= e($e['titre'] ?? '') ?></p>
                <?php if (!empty($e['lieu'])): ?>
                  <p class="agenda-court__lieu"><?= e($e['lieu']) ?></p>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <a class="lien-fleche" href="<?= route('agenda') ?>"><?= e(t('Voir l’agenda')) ?></a>
        </aside>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php /* Le village, en photo et en texte : c'est la section qui parle au
         nouvel arrivant et au visiteur de passage. */ ?>
<?php if (!empty($page['village'])): ?>
<section class="section">
  <div class="conteneur">
    <div class="duo">
      <div class="duo__media reveler">
        <img src="<?= image($page['village']['image']) ?>"
             alt="<?= e($page['village']['image_alt'] ?? '') ?>" loading="lazy">
      </div>

      <div class="duo__texte reveler">
        <p class="surtitre"><?= e($page['village']['surtitre'] ?? '') ?></p>
        <h2 class="titre-section"><?= e($page['village']['titre'] ?? '') ?></h2>
        <?php foreach ($page['village']['paragraphes'] ?? [] as $p): ?>
          <p><?= e($p) ?></p>
        <?php endforeach; ?>

        <?php if (!empty($page['village']['points'])): ?>
          <ul class="points">
            <?php foreach ($page['village']['points'] as $point): ?>
              <li class="points__item">
                <span class="points__numero"><?= e($point['numero'] ?? '') ?></span>
                <div>
                  <h3 class="points__titre"><?= e($point['titre'] ?? '') ?></h3>
                  <p><?= e($point['texte'] ?? '') ?></p>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php if (!empty($page['village']['lien']['url'])): ?>
          <a class="lien-fleche" href="<?= lien($page['village']['lien']['url']) ?>">
            <?= e($page['village']['lien']['libelle']) ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($page['indicateurs']['items'])): ?>
<section class="indicateurs">
  <div class="conteneur">
    <?php if (!empty($page['indicateurs']['titre'])): ?>
      <p class="indicateurs__intro reveler"><?= e($page['indicateurs']['titre']) ?></p>
    <?php endif; ?>
    <ul class="indicateurs__liste">
      <?php foreach ($page['indicateurs']['items'] as $i): ?>
        <li class="indicateurs__item reveler">
          <p class="indicateurs__valeur">
            <?= e($i['valeur']) ?><span class="indicateurs__unite"><?= e($i['unite']) ?></span>
          </p>
          <p class="indicateurs__libelle"><?= e($i['libelle']) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php /* Les rubriques du site en fin de page : celui qui a défilé jusqu'ici
         n'a pas trouvé ce qu'il cherchait dans les démarches. Six portes
         d'entrée valent mieux qu'un menu déroulant à rouvrir. */ ?>
<?php if (!empty($page['rubriques']['items'])): ?>
<section class="section section--teinte">
  <div class="conteneur">
    <div class="section__tete section__tete--centre reveler">
      <p class="surtitre"><?= e($page['rubriques']['surtitre'] ?? '') ?></p>
      <h2 class="titre-section"><?= e($page['rubriques']['titre'] ?? '') ?></h2>
    </div>
    <ul class="cartes cartes--rubriques">
      <?php foreach ($page['rubriques']['items'] as $item): ?>
        <li class="carte-rubrique reveler">
          <a href="<?= lien($item['lien']['url'] ?? '/') ?>">
            <span class="carte-rubrique__icone" aria-hidden="true">
              <?= $view->partial('icones', ['nom' => $item['icone'] ?? 'document']) ?>
            </span>
            <h3 class="carte-rubrique__titre"><?= e($item['titre'] ?? '') ?></h3>
            <p class="carte-rubrique__texte"><?= e($item['texte'] ?? '') ?></p>
            <span class="carte-rubrique__lien lien-fleche"><?= e($item['lien']['libelle'] ?? t('Découvrir')) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?= $view->partial('bande-cta') ?>
