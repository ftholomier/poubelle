<?php
/** Bandeau de consentement et panneau « Gestion des cookies » (affichés par site.js). @var array $g */
$c = (array) ($g['cookies'] ?? []);
$cats = LD\Consent::categories();
$text = strtr((string) ($c['text'] ?? ''), ['{audience}' => in_array('audience', $cats, true) ? (string) ($c['text_audience'] ?? '') : '']);
$cat = static function (string $key) use ($c): string {
    return '<div class="ccp__cat"><label class="ccp__head"><b>' . t($c[$key . '_title'] ?? $key) . '</b>'
        . '<span class="ccp__sw"><input type="checkbox" data-cc-cat="' . e($key) . '"><i aria-hidden="true"></i></span></label>'
        . '<p>' . t($c[$key . '_text'] ?? '') . '</p></div>';
};
?>
<div class="cc" id="cc-banner" role="region" aria-label="<?= e($c['title'] ?? 'Cookies') ?>" hidden>
  <button type="button" class="cc__skip" data-cc="refuse"><?= t($c['continue'] ?? 'Continuer sans accepter') ?> <span aria-hidden="true">×</span></button>
  <p class="cc__t"><?= t($c['title'] ?? '') ?></p>
  <p class="cc__p"><?= t($text) ?> <a href="/mentions-legales#cookies"><?= t($c['policy_link'] ?? 'En savoir plus') ?></a></p>
  <div class="cc__btns">
    <button type="button" class="cc-btn cc-btn--ghost" data-cc="customize"><?= t($c['customize'] ?? 'Personnaliser') ?></button>
    <button type="button" class="cc-btn cc-btn--dark" data-cc="refuse"><?= t($c['refuse'] ?? 'Tout refuser') ?></button>
    <button type="button" class="cc-btn cc-btn--red" data-cc="accept"><?= t($c['accept'] ?? 'Tout accepter') ?></button>
  </div>
</div>
<div class="ccp" id="cc-panel" role="dialog" aria-modal="true" aria-labelledby="cc-panel-title" hidden>
  <div class="ccp__card" tabindex="-1">
    <button type="button" class="ccp__close" data-cc="close" aria-label="Fermer"><?= icon('close', 14, 'currentColor', 2.4) ?></button>
    <h2 class="ccp__t" id="cc-panel-title"><?= t($c['panel_title'] ?? 'Gestion des cookies') ?></h2>
    <p class="ccp__p"><?= t($c['panel_text'] ?? '') ?></p>
    <div class="ccp__cat">
      <div class="ccp__head"><b><?= t($c['essential_title'] ?? 'Essentiels') ?></b><span class="ccp__always"><?= t($c['always'] ?? 'Toujours actifs') ?></span></div>
      <p><?= t($c['essential_text'] ?? '') ?></p>
    </div>
    <?php foreach ($cats as $k): ?><?= $cat($k) ?><?php endforeach; ?>
    <div class="ccp__btns">
      <button type="button" class="cc-btn cc-btn--dark" data-cc="refuse"><?= t($c['refuse'] ?? 'Tout refuser') ?></button>
      <button type="button" class="cc-btn cc-btn--red" data-cc="accept"><?= t($c['accept'] ?? 'Tout accepter') ?></button>
      <?php if ($cats): ?><button type="button" class="cc-btn cc-btn--ghost" data-cc="save"><?= t($c['save'] ?? 'Enregistrer mes choix') ?></button><?php endif; ?>
    </div>
    <a class="ccp__more" href="/mentions-legales#cookies"><?= t($c['policy_link'] ?? 'En savoir plus') ?></a>
  </div>
</div>
