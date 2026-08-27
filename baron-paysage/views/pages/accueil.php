<?php
/**
 * Page d'accueil.
 *
 * @var array $page
 * @var array $services
 * @var array $valeurs
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

// Ordre aléatoire, au choix du client. Le tirage est fait ici, à chaque
// affichage : le visiteur qui revient ne retombe pas sur la même première
// image, et un jardin d'automne a autant de chances d'ouvrir la page qu'une
// piscine d'été. L'ordre rangé au back-office n'est pas touché — décocher la
// case le rend tel quel.
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

<?php if (!empty($page['indicateurs']['items'])): ?>
<section class="indicateurs">
  <div class="conteneur">
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

<section class="section" id="services">
  <div class="conteneur">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e($page['services']['surtitre']) ?></p>
      <h2 class="titre-section"><?= e($page['services']['titre']) ?></h2>
      <p class="section__chapo"><?= e($page['services']['texte']) ?></p>
    </div>

    <ul class="cartes cartes--services">
      <?php foreach ($services as $service): ?>
        <li class="carte-service reveler">
          <a href="<?= route('nos-services', $service['slug']) ?>">
            <figure class="carte-service__media">
              <img src="<?= image($service['image'] ?? '', true) ?>" alt="<?= e($service['nom']) ?>" loading="lazy">
              <span class="carte-service__icone" aria-hidden="true">
                <?= $view->partial('icones', ['nom' => $service['icone'] ?? 'feuille']) ?>
              </span>
            </figure>
            <h3 class="carte-service__titre"><?= e($service['nom']) ?></h3>
            <p class="carte-service__texte"><?= e($service['resume']) ?></p>
            <span class="carte-service__lien lien-fleche"><?= e(t('En savoir plus')) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if (!empty($page['services']['lien']['url'])): ?>
      <p class="section__pied reveler">
        <a class="btn btn--contour" href="<?= lien($page['services']['lien']['url']) ?>">
          <?= e($page['services']['lien']['libelle']) ?>
        </a>
      </p>
    <?php endif; ?>
  </div>
</section>

<section class="section section--teinte">
  <div class="conteneur">
    <div class="duo">
      <div class="duo__media reveler">
        <img src="<?= image($page['societe']['image']) ?>"
             alt="<?= e($site['nom']) ?> — <?= e($page['societe']['titre']) ?>" loading="lazy">
      </div>

      <div class="duo__texte reveler">
        <p class="surtitre"><?= e($page['societe']['surtitre']) ?></p>
        <h2 class="titre-section"><?= e($page['societe']['titre']) ?></h2>
        <?php foreach ($page['societe']['paragraphes'] as $p): ?>
          <p><?= e($p) ?></p>
        <?php endforeach; ?>

        <ul class="points">
          <?php foreach ($page['societe']['points'] as $point): ?>
            <li class="points__item">
              <span class="points__numero"><?= e($point['numero']) ?></span>
              <div>
                <h3 class="points__titre"><?= e($point['titre']) ?></h3>
                <p><?= e($point['texte']) ?></p>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>

        <?php if (!empty($page['societe']['lien']['url'])): ?>
          <a class="lien-fleche" href="<?= lien($page['societe']['lien']['url']) ?>">
            <?= e($page['societe']['lien']['libelle']) ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php $citation = $page['citation'] ?? []; ?>
<?php /* Vidé depuis le back-office, le champ reste présent mais vide :
         c'est le contenu, pas la clé, qui décide de l'affichage. */ ?>
<?php if (trim((string) ($citation['texte'] ?? '')) !== ''): ?>
<?php /* La phrase du dirigeant, posée seule sur l'ardoise de la charte.
         Elle sépare la présentation de l'entreprise de ses valeurs, et
         c'est le seul endroit du site où le texte porte la page. */ ?>
<section class="citation">
  <div class="conteneur conteneur--etroit">
    <figure class="citation__bloc reveler">
      <span class="citation__feuille" aria-hidden="true">
        <?= $view->partial('icones', ['nom' => 'feuille']) ?>
      </span>
      <blockquote class="citation__texte"><p><?= e($citation['texte']) ?></p></blockquote>
      <?php if (trim((string) ($citation['auteur'] ?? '')) !== ''): ?>
        <figcaption class="citation__auteur"><?= e($citation['auteur']) ?></figcaption>
      <?php endif; ?>
    </figure>
  </div>
</section>
<?php endif; ?>

<?php if ($valeurs !== []): ?>
<section class="section">
  <div class="conteneur">
    <div class="section__tete section__tete--centre reveler">
      <p class="surtitre"><?= e($page['valeurs']['surtitre']) ?></p>
      <h2 class="titre-section"><?= e($page['valeurs']['titre']) ?></h2>
      <p class="section__chapo"><?= e($page['valeurs']['texte']) ?></p>
    </div>

    <?php /* Trois mots, trois pictos, rien d'autre : le client a demandé que
             les descriptions disparaissent de l'accueil. Elles n'ont pas été
             supprimées pour autant — la page « Nos valeurs » les développe,
             et c'est là qu'on les lit quand on veut les lire. */ ?>
    <ul class="valeurs-apercu">
      <?php foreach ($valeurs as $valeur): ?>
        <li class="valeurs-apercu__item reveler">
          <a href="<?= route('nos-valeurs') ?>#<?= e($valeur['slug']) ?>">
            <span class="valeurs-apercu__picto" aria-hidden="true">
              <?= $view->partial('icones', ['nom' => $valeur['icone'] ?? 'feuille']) ?>
            </span>
            <h3 class="valeurs-apercu__titre"><?= e($valeur['nom']) ?></h3>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if (!empty($page['valeurs']['lien']['url'])): ?>
      <p class="section__pied section__pied--centre reveler">
        <a class="btn btn--contour" href="<?= lien($page['valeurs']['lien']['url']) ?>">
          <?= e($page['valeurs']['lien']['libelle']) ?>
        </a>
      </p>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if (($apercu ?? []) !== []): ?>
<?php /* Une galerie ne se raconte pas, elle se montre. Le bloc reprend les
         premières photos de la page « Réalisations » : rien à tenir à jour
         ici quand le client en ajoute une. */ ?>
<section class="section section--teinte">
  <div class="conteneur">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e($page['realisations']['surtitre']) ?></p>
      <h2 class="titre-section"><?= e($page['realisations']['titre']) ?></h2>
      <p class="section__chapo"><?= e($page['realisations']['texte']) ?></p>
    </div>

    <?= $view->partial('galerie', ['items' => $apercu, 'etiquettes' => false]) ?>

    <?php if (!empty($page['realisations']['lien']['url'])): ?>
      <p class="section__pied reveler">
        <a class="btn btn--contour" href="<?= lien($page['realisations']['lien']['url']) ?>">
          <?= e($page['realisations']['lien']['libelle']) ?>
        </a>
      </p>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="conteneur">
    <div class="serenite">
      <div class="serenite__media reveler">
        <img src="<?= image($page['serenite']['image']) ?>"
             alt="<?= e($page['serenite']['titre']) ?>" loading="lazy">
        <p class="serenite__chiffre"><?= e($page['serenite']['chiffre']) ?></p>
      </div>

      <div class="serenite__texte reveler">
        <p class="surtitre"><?= e($page['serenite']['surtitre']) ?></p>
        <h2 class="titre-section"><?= e($page['serenite']['titre']) ?></h2>
        <p><?= e($page['serenite']['texte']) ?></p>

        <ul class="liste-cochee">
          <?php foreach ($page['serenite']['arguments'] as $argument): ?>
            <li><?= e($argument) ?></li>
          <?php endforeach; ?>
        </ul>

        <p class="serenite__legende"><?= e($page['serenite']['legende']) ?></p>
      </div>
    </div>
  </div>
</section>

<?= $view->partial('bande-cta') ?>
