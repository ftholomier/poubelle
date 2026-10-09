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
        <?php if ($shop && ($fb = \App\Front\Menus::button('boutique'))): ?><a class="hshop" href="<?= e($fb['href']) ?>"><?= e($fb['label']) ?></a><?php endif; ?>
        <?php if ($fb = \App\Front\Menus::button('don')): ?><a class="hdon" href="<?= e($fb['href']) ?>">♥ <?= e($fb['label']) ?></a><?php endif; ?>
      </div>
      <?php if ($social): ?>
      <div class="mf-social" aria-label="<?= e(t('Réseaux sociaux')) ?>">
        <?php foreach ($social as $k => [$label, $href]): ?>
          <a href="<?= e($href) ?>" rel="noopener" target="_blank" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= \App\Core\View::partial('vitrine/partials/icon', ['name' => $k]) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php foreach (\App\Front\Menus::get()['footer'] as $col): $links = \App\Front\Menus::links((array) ($col['links'] ?? [])); if (!$links) continue; $ct = \App\Front\Menus::text($col, 'title'); ?>
    <nav class="mf-col" aria-label="<?= e($ct) ?>">
      <h2><?= e($ct) ?></h2>
      <?php foreach ($links as $lk): ?><a href="<?= e($lk['href']) ?>"<?= $lk['ext'] ? ' rel="noopener" target="_blank"' : '' ?>><?= e($lk['label']) ?><?= $lk['ext'] ? ' ↗' : '' ?></a><?php endforeach; ?>
    </nav>
    <?php endforeach; ?>
    <?php $pick = \App\Shop\ShopPages::homePicks(1)[0] ?? null; if ($pick): ?>
    <div class="mf-col mf-shop">
      <h2><?= e(t('À la boutique')) ?></h2>
      <a class="mf-shop__card" href="<?= e($pick['href']) ?>">
        <span class="mf-shop__img"><img src="<?= e($pick['img']) ?>" alt="" loading="lazy" decoding="async"></span>
        <span class="mf-shop__txt"><small><?= e($pick['kind']) ?></small><b><?= e($pick['name']) ?></b><span><?= e($pick['price']) ?></span></span>
      </a>
      <a class="mf-shop__go" href="<?= e(url('/boutique/')) ?>"><?= e(t('Personnalisez-le, commandez : chaque achat fait vivre le musée')) ?> →</a>
    </div>
    <?php endif; ?>
  </div>
  <div class="site-footer__bar">
    <?php if (($copy = $ft('copyright')) !== ''): ?><span><?= e(str_replace(['{année}', '{annee}', '{year}'], date('Y'), $copy)) ?></span><?php endif; ?>
    <a href="<?= e(url((string) Settings::get('privacy.legal_page', '/mentions-legales/'))) ?>"><?= e(t('Mentions légales')) ?></a>
    <a href="<?= e(url('/confidentialite/')) ?>"><?= e(t('Confidentialité')) ?></a>
    <a href="<?= e(url('/cookies/')) ?>"><?= e(t('Cookies')) ?></a>
    <?php if ($credit !== '' || $creditName !== ''): ?><span><?= e($credit) ?><?php if ($creditName !== ''): ?> <a href="<?= e($creditLink) ?>" rel="noopener" target="_blank"><?= e($creditName) ?></a><?php endif; ?></span><?php endif; ?>
  </div>
</footer>
