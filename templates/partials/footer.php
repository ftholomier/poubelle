<?php
use App\Core\Settings;

$social = array_filter([
    'f' => ['Facebook', Settings::get('social.facebook')],
    'ig' => ['Instagram', Settings::get('social.instagram')],
    'X' => ['X', Settings::get('social.x')],
    'yt' => ['YouTube', Settings::get('social.youtube')],
], fn ($s) => !empty($s[1]));
?>
<footer class="site-footer">
  <div class="site-footer__main">
    <div class="stack gap-14">
      <h2><?= e(t('Un partenariat ?')) ?><br><?= e(t("Besoin d'une information ?")) ?></h2>
      <p class="text-lg" style="margin:0;font-size:19px"><?= e(t('On vous répond dans les plus brefs délais.')) ?></p>
      <div class="row gap-14">
        <a class="btn btn--yellow" href="<?= e(url('/contact/')) ?>" style="font-size:17px;padding:14px 22px"><?= e(t('Contactez-nous')) ?></a>
        <a class="btn btn--ghost-light" href="<?= e(url('/supporters/l-equipe-de-sochaux-retro/')) ?>" style="font-size:17px;padding:12px 20px"><?= e(t("L'équipe Sochaux rétro")) ?></a>
      </div>
    </div>
    <div class="site-footer__brand">
      <img src="/assets/img/logo-sochaux-retro.png" alt="Sochaux rétro" width="85" height="96" loading="lazy">
      <span class="tag"><?= e(t('Le musée en ligne du FCSM')) ?></span>
      <div class="social">
        <?php foreach ($social as $k => [$label, $href]): ?>
          <a href="<?= e($href) ?>" rel="noopener" target="_blank" aria-label="<?= e($label) ?>"><?= e($k) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="site-footer__bar">
    <span>© <?= date('Y') ?> FC Sochaux rétro</span>
    <a href="<?= e(url((string) Settings::get('privacy.legal_page', '/mentions-legales/'))) ?>"><?= e(t('Mentions légales')) ?></a>
    <a href="<?= e(url('/confidentialite/')) ?>"><?= e(t('Confidentialité')) ?></a>
    <a href="#" data-cookie-open><?= e(t('Cookies')) ?></a>
    <a class="don" href="<?= e(url('/faire-un-don/')) ?>"><?= e(t('Faire un don')) ?></a>
    <span><?= e(t('Propulsé par')) ?> <a href="https://www.le-digital.com" rel="noopener" target="_blank">LE-DIGITAL.com</a></span>
  </div>
</footer>
