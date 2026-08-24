<?php
/**
 * Page « Questions fréquentes ».
 *
 * La FAQ a quitté le bas de la page de devis pour sa propre adresse : c'est
 * le contenu qu'on cherche avant de demander un prix, et une question porte
 * son propre référencement.
 *
 * @var array $page
 * @var App\Core\Content $content
 * @var App\Core\View $view
 */
$site = $content->load('site');
$tel = (string) ($site['contact']['telephone'] ?? '');
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<?php if (!empty($page['faq']['items'])): ?>
<section class="section">
  <div class="conteneur conteneur--etroit">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e($page['faq']['surtitre']) ?></p>
      <h2 class="titre-section"><?= e($page['faq']['titre']) ?></h2>
    </div>

    <div class="faq reveler">
      <?php foreach ($page['faq']['items'] as $i => $question): ?>
        <details class="faq__item"<?= $i === 0 ? ' open' : '' ?>>
          <summary class="faq__question"><?= e($question['question']) ?></summary>
          <div class="faq__reponse"><p><?= e($question['reponse']) ?></p></div>
        </details>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($page['faq']['relance']['titre'])): ?>
      <div class="encadre reveler">
        <h3><?= e($page['faq']['relance']['titre']) ?></h3>
        <p><?= e($page['faq']['relance']['texte']) ?></p>
        <?php if ($tel !== ''): ?>
          <a class="btn btn--orange" href="<?= e(tel_lien($tel)) ?>">
            <?= e(t('Appeler le')) ?> <?= e($tel) ?>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?= $view->partial('bande-cta') ?>
