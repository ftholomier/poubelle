<?php
/**
 * Page contact : coordonnées et plans d'accès des deux implantations.
 *
 * Le formulaire vit sur la page « Demander un devis » : quelqu'un qui
 * cherche une adresse et quelqu'un qui engage un projet n'ont pas la même
 * intention, et mêler les deux dilue la page qui convertit.
 *
 * @var array $page
 * @var App\Core\Content $content
 * @var App\Core\View $view
 */
$site = $content->load('site');
$tel  = (string) ($site['contact']['telephone'] ?? '');
$mail = (string) ($site['contact']['email'] ?? '');
$reseaux = array_filter((array) ($site['reseaux'] ?? []));
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<section class="section">
  <div class="conteneur">
    <div class="section__tete reveler">
      <h2 class="titre-section"><?= e($page['introduction']['titre']) ?></h2>
      <p class="section__chapo"><?= e($page['introduction']['texte']) ?></p>
    </div>

    <?php /* Les deux implantations côte à côte, chacune avec son plan : c'est
             la question posée le plus souvent, « laquelle est la plus proche
             de chez moi ». Une seule adresse en tête de page obligeait à
             chercher la seconde. */ ?>
    <div class="implantations">
      <?php foreach ($page['implantations'] ?? [] as $implantation): ?>
        <?= $view->partial('carte', ['implantation' => $implantation]) ?>
      <?php endforeach; ?>
    </div>

    <ul class="coordonnees coordonnees--rangee">
      <?php if ($tel !== ''): ?>
        <li class="coordonnees__item reveler">
          <span class="coordonnees__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'telephone']) ?></span>
          <div>
            <p class="coordonnees__label"><?= e(t('Téléphone')) ?></p>
            <a class="coordonnees__valeur" href="<?= e(tel_lien($tel)) ?>"><?= e($tel) ?></a>
          </div>
        </li>
      <?php endif; ?>

      <?php if ($mail !== ''): ?>
        <li class="coordonnees__item reveler">
          <span class="coordonnees__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'courriel']) ?></span>
          <div>
            <p class="coordonnees__label"><?= e(t('Adresse électronique')) ?></p>
            <a class="coordonnees__valeur" href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a>
          </div>
        </li>
      <?php endif; ?>

      <?php if (($site['contact']['horaires'] ?? '') !== ''): ?>
        <li class="coordonnees__item reveler">
          <span class="coordonnees__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'horaires']) ?></span>
          <div>
            <p class="coordonnees__label"><?= e(t('Horaires d’ouverture')) ?></p>
            <p class="coordonnees__valeur"><?= e($site['contact']['horaires']) ?></p>
          </div>
        </li>
      <?php endif; ?>

      <?php if ($reseaux !== []): ?>
        <li class="coordonnees__item reveler">
          <span class="coordonnees__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'instagram']) ?></span>
          <div>
            <p class="coordonnees__label"><?= e(t('Nous suivre')) ?></p>
            <p class="coordonnees__reseaux">
              <?php foreach ($reseaux as $nom => $url): ?>
                <a href="<?= e($url) ?>" target="_blank" rel="noopener me">
                  <span aria-hidden="true"><?= $view->partial('icones', ['nom' => $nom]) ?></span>
                  <?= e(ucfirst($nom)) ?>
                </a>
              <?php endforeach; ?>
            </p>
          </div>
        </li>
      <?php endif; ?>
    </ul>
  </div>
</section>

<?php if (($page['relance']['titre'] ?? '') !== ''): ?>
<section class="section section--teinte">
  <div class="conteneur conteneur--etroit">
    <div class="encadre reveler">
      <h3><?= e($page['relance']['titre']) ?></h3>
      <p><?= e($page['relance']['texte']) ?></p>
      <?php if (($page['relance']['lien']['url'] ?? '') !== ''): ?>
        <a class="btn btn--vert" href="<?= lien($page['relance']['lien']['url']) ?>">
          <?= e($page['relance']['lien']['libelle']) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?= $view->partial('bande-cta') ?>
