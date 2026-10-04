<?php
/** Pied de page : textes, boutons et mentions réglables (Réglages › Pied de page), réseaux sociaux. */
use App\Core\Settings;

// Texte réglé ; en anglais, la version anglaise saisie, sinon la traduction d'origine tant que
// le texte français est celui de départ.
$defaults = Settings::defaults();
$ft = function (string $k) use ($defaults): string {
    $fr = trim((string) Settings::get("footer.$k", ''));
    if (!\App\Services\I18n::isEn()) {
        return $fr;
    }
    $en = trim((string) Settings::get("footer.{$k}_en", ''));
    return $en !== '' ? $en : ($fr === ($defaults["footer.$k"] ?? null) ? t($fr) : $fr);
};
// Lien réglé : page du musée (/…, dans la langue de la page) ou adresse web ; sinon celui de départ.
$fl = function (string $k) use ($defaults): string {
    $h = trim((string) Settings::get("footer.$k", ''));
    if (!preg_match('#^(/(?!/)|https?://)#i', $h)) {
        $h = (string) ($defaults["footer.$k"] ?? '/');
    }
    return str_starts_with($h, '/') ? url($h) : $h;
};
$title = array_filter([$ft('title'), $ft('title2')], fn ($l) => $l !== '');
$buttons = array_filter([['btn btn--yellow', $ft('button1'), $fl('button1_link'), 'font-size:17px;padding:14px 22px'], ['btn btn--ghost-light', $ft('button2'), $fl('button2_link'), 'font-size:17px;padding:12px 20px']], fn ($b) => $b[1] !== '');
$credit = $ft('credit');
$creditName = trim((string) Settings::get('footer.credit_name', ''));
$creditLink = $fl('credit_link');

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
      <?php if ($title): ?><h2><?= implode('<br>', array_map('e', $title)) ?></h2><?php endif; ?>
      <?php if (($text = $ft('text')) !== ''): ?><p class="text-lg" style="margin:0;font-size:19px"><?= e($text) ?></p><?php endif; ?>
      <?php if ($buttons): ?>
      <div class="row gap-14">
        <?php foreach ($buttons as [$class, $label, $href, $style]): ?>
          <a class="<?= $class ?>" href="<?= e($href) ?>" style="<?= $style ?>"<?= str_starts_with($href, 'http') ? ' rel="noopener" target="_blank"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="site-footer__brand">
      <img src="/assets/img/logo-sochaux-retro.png" alt="Sochaux rétro" width="85" height="96" loading="lazy">
      <?php if (($tag = $ft('tagline')) !== ''): ?><span class="tag"><?= e($tag) ?></span><?php endif; ?>
      <div class="social">
        <?php foreach ($social as $k => [$label, $href]): ?>
          <a href="<?= e($href) ?>" rel="noopener" target="_blank" aria-label="<?= e($label) ?>"><?= e($k) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="site-footer__bar">
    <?php if (($copy = $ft('copyright')) !== ''): ?><span><?= e(str_replace(['{année}', '{annee}', '{year}'], date('Y'), $copy)) ?></span><?php endif; ?>
    <a href="<?= e(url((string) Settings::get('privacy.legal_page', '/mentions-legales/'))) ?>"><?= e(t('Mentions légales')) ?></a>
    <a href="<?= e(url('/confidentialite/')) ?>"><?= e(t('Confidentialité')) ?></a>
    <a href="<?= e(url('/cookies/')) ?>"><?= e(t('Cookies')) ?></a>
    <a class="don" href="<?= e(url('/faire-un-don/')) ?>"><?= e(t('Faire un don')) ?></a>
    <?php if ($credit !== '' || $creditName !== ''): ?><span><?= e($credit) ?><?php if ($creditName !== ''): ?> <a href="<?= e($creditLink) ?>" rel="noopener" target="_blank"><?= e($creditName) ?></a><?php endif; ?></span><?php endif; ?>
  </div>
</footer>
