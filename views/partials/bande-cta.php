<?php
/**
 * Bande d'appel à l'action, commune au bas de toutes les pages.
 *
 * @var App\Core\Content $content
 * @var App\Core\View $view
 */
$site = $content->load('site');
$resa = $site['reservation'];
$tel  = (string) ($site['contact']['telephone'] ?? '');
?>
<section class="bande-cta">
  <div class="conteneur reveler">
    <p class="surtitre surtitre--clair surtitre--centre"><?= e(t('Parlons de votre activité')) ?></p>
    <h2 class="bande-cta__titre"><?= e(t('Un premier échange, sans engagement')) ?></h2>
    <p class="bande-cta__texte">
      <?= e(t('Décrivez-nous votre situation en quelques lignes, ou appelez-nous directement. Nous prenons le temps de comprendre votre projet avant de vous proposer quoi que ce soit.')) ?>
    </p>
    <div class="bande-cta__actions">
      <?php if (($resa['principal']['url'] ?? '') !== ''): ?>
        <a class="btn btn--orange" href="<?= lien($resa['principal']['url']) ?>">
          <?= e($resa['principal']['libelle']) ?>
        </a>
      <?php endif; ?>
      <?php if ($tel !== ''): ?>
        <a class="btn btn--contour-clair" href="<?= e(tel_lien($tel)) ?>">
          <?= e(t('Appeler le')) ?> <?= e($tel) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>
