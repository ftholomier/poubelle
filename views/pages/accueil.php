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
$pause = max(2, (int) ($hero['diaporama']['pause'] ?? 6));
?>

<section class="heros heros--accueil" style="--pause: <?= $pause ?>s">
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
      <a class="btn btn--orange" href="<?= lien($hero['bouton_principal']['url']) ?>">
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
                <?= $view->partial('icones', ['nom' => $service['icone'] ?? 'pergola-bioclimatique']) ?>
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

<?php $france = $page['france'] ?? []; ?>
<?php /* Vidés depuis le back-office, les champs restent présents mais vides :
         c'est le contenu, pas la clé, qui décide de l'affichage. */ ?>
<?php if (trim(($france['titre'] ?? '') . ($france['texte'] ?? '')) !== ''): ?>
<?php /* Bande « Fabriqué en France ». Elle est posée juste après la
         présentation de la société : l'origine de fabrication est un
         argument sur l'entreprise avant d'être un argument sur le produit. */ ?>
<section class="france">
  <div class="conteneur">
    <div class="france__grille reveler">
      <div class="france__texte">
        <p class="france__drapeau" aria-hidden="true">
          <span class="france__bande france__bande--bleu"></span>
          <span class="france__bande france__bande--blanc"></span>
          <span class="france__bande france__bande--rouge"></span>
        </p>
        <p class="surtitre surtitre--clair"><?= e($france['surtitre']) ?></p>
        <h2 class="france__titre"><?= e($france['titre']) ?></h2>
        <p class="france__chapo"><?= e($france['texte']) ?></p>
      </div>

      <?php if (!empty($france['points'])): ?>
        <ul class="france__points">
          <?php foreach ($france['points'] as $point): ?>
            <li><?= e($point) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($valeurs !== []): ?>
<section class="section">
  <div class="conteneur">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e($page['valeurs']['surtitre']) ?></p>
      <h2 class="titre-section"><?= e($page['valeurs']['titre']) ?></h2>
      <p class="section__chapo"><?= e($page['valeurs']['texte']) ?></p>
    </div>

    <ul class="valeurs-apercu">
      <?php foreach ($valeurs as $valeur): ?>
        <li class="valeurs-apercu__item reveler">
          <figure class="valeurs-apercu__media">
            <img src="<?= image($valeur['image'], true) ?>" alt="<?= e($valeur['nom']) ?>" loading="lazy">
          </figure>
          <h3 class="valeurs-apercu__titre"><?= e($valeur['nom']) ?></h3>
          <?php /* Le résumé est rédigé pour tenir en trois lignes. À défaut,
                   le premier paragraphe le remplace, tronqué par le CSS. */ ?>
          <p class="valeurs-apercu__texte">
            <?= e($valeur['resume'] ?? '' ?: ($valeur['paragraphes'][0] ?? '')) ?>
          </p>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if (!empty($page['valeurs']['lien']['url'])): ?>
      <p class="section__pied reveler">
        <a class="btn btn--contour" href="<?= lien($page['valeurs']['lien']['url']) ?>">
          <?= e($page['valeurs']['lien']['libelle']) ?>
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
