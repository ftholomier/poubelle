<?php
/**
 * Pied de page du musée : appel réglable (Réglages › Pied de page), plan du musée, boutons
 * Boutique et Don, réseaux sociaux, lien vers le site de l'association, mentions.
 */
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
    'facebook' => ['Facebook', Settings::get('social.facebook')],
    'instagram' => ['Instagram', Settings::get('social.instagram')],
    'x' => ['X', Settings::get('social.x')],
    'youtube' => ['YouTube', Settings::get('social.youtube')],
], fn ($s) => !empty($s[1]));
$shop = \App\Shop\ShopPages::visible();
$asso = \App\Vitrine\Host::base();
$explore = [[t('Matchs'), '/matchs/'], [t('Saisons'), '/saisons/'], [t('Nos Lions'), '/nos-lions/'], [t('Face-à-face'), '/face-a-face/'], [t('Records'), '/records/'], [t('Les chiffres'), '/chiffres/'], [t('Le centenaire'), '/centenaire/']];
$play = [[t('Rétro-Direct'), '/interactif/retro-direct/'], [t('Quiz'), '/interactif/quiz/'], [t('Album'), '/interactif/album/'], [t('Fil jaune'), '/interactif/fil-jaune/'], [t('Frise'), '/interactif/frise/'], [t('Tout Interactif'), '/interactif/']];
$join = [[t('Contribuer'), '/contribuer/'], [t('La newsletter'), '/newsletter/'], [t('Nous contacter'), '/contact/']];
?>
<footer class="site-footer">
  <?php if ($title || $buttons): ?>
  <div class="mf-cta">
    <div class="mf-cta__in">
      <div class="stack gap-14">
        <?php if ($title): ?><h2><?= implode('<br>', array_map('e', $title)) ?></h2><?php endif; ?>
        <?php if (($text = $ft('text')) !== ''): ?><p class="mf-cta__txt"><?= e($text) ?></p><?php endif; ?>
      </div>
      <?php if ($buttons): ?>
      <div class="mf-cta__btns">
        <?php foreach ($buttons as [$class, $label, $href]): ?>
          <a class="<?= $class ?>" href="<?= e($href) ?>"<?= str_starts_with($href, 'http') ? ' rel="noopener" target="_blank"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="mf-main">
    <div class="mf-brand">
      <a class="mf-logo" href="<?= e(url('/')) ?>"><img src="/assets/img/logo-sochaux-retro.png" alt="" width="70" height="79" loading="lazy"><span><b>Sochaux Rétro</b><?php if (($tag = $ft('tagline')) !== ''): ?><small><?= e($tag) ?></small><?php endif; ?></span></a>
      <div class="mf-btns">
        <?php if ($shop): ?><a class="hshop" href="<?= e(url('/boutique/')) ?>"><?= e(t('La boutique')) ?></a><?php endif; ?>
        <a class="hdon" href="<?= e(url('/faire-un-don/')) ?>">♥ <?= e(t('Faire un don')) ?></a>
      </div>
      <?php if ($social): ?>
      <div class="mf-social" aria-label="<?= e(t('Réseaux sociaux')) ?>">
        <?php foreach ($social as $k => [$label, $href]): ?>
          <a href="<?= e($href) ?>" rel="noopener" target="_blank" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= \App\Core\View::partial('vitrine/partials/icon', ['name' => $k]) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <nav class="mf-col" aria-label="<?= e(t('Explorer')) ?>">
      <h2><?= e(t('Explorer')) ?></h2>
      <?php foreach ($explore as [$l, $h]): ?><a href="<?= e(url($h)) ?>"><?= e($l) ?></a><?php endforeach; ?>
    </nav>
    <nav class="mf-col" aria-label="<?= e(t('Interactif')) ?>">
      <h2><?= e(t('Interactif')) ?></h2>
      <?php foreach ($play as [$l, $h]): ?><a href="<?= e(url($h)) ?>"><?= e($l) ?></a><?php endforeach; ?>
    </nav>
    <nav class="mf-col" aria-label="<?= e(t('Participer')) ?>">
      <h2><?= e(t('Participer')) ?></h2>
      <?php foreach ($join as [$l, $h]): ?><a href="<?= e(url($h)) ?>"><?= e($l) ?></a><?php endforeach; ?>
      <?php if ($shop): ?><a href="<?= e(url('/boutique/')) ?>"><?= e(t('La boutique')) ?></a><?php endif; ?>
      <a href="<?= e($asso) ?>" rel="noopener" target="_blank"><?= e(t('L’association Sochaux Rétro')) ?> ↗</a>
    </nav>
  </div>
  <div class="site-footer__bar">
    <?php if (($copy = $ft('copyright')) !== ''): ?><span><?= e(str_replace(['{année}', '{annee}', '{year}'], date('Y'), $copy)) ?></span><?php endif; ?>
    <a href="<?= e(url((string) Settings::get('privacy.legal_page', '/mentions-legales/'))) ?>"><?= e(t('Mentions légales')) ?></a>
    <a href="<?= e(url('/confidentialite/')) ?>"><?= e(t('Confidentialité')) ?></a>
    <a href="<?= e(url('/cookies/')) ?>"><?= e(t('Cookies')) ?></a>
    <?php if ($credit !== '' || $creditName !== ''): ?><span><?= e($credit) ?><?php if ($creditName !== ''): ?> <a href="<?= e($creditLink) ?>" rel="noopener" target="_blank"><?= e($creditName) ?></a><?php endif; ?></span><?php endif; ?>
  </div>
</footer>
