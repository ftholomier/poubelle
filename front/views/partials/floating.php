<?php
$site = Content::site();
$ui = Content::ui();
$bot = $ui['bot'] ?? [];
$exit = $ui['exit'] ?? [];
$teaser = $ui['teaser'] ?? [];
$devis = Content::path('devis');
?>
<?php if (!empty($ui['cta_bar']['enabled'])): ?>
<div class="rv-cta" id="rv-cta">
  <div class="rv-cta__box">
    <a class="btn rv-cta__c" href="<?= e(Content::path('contact')) ?>"><span class="fx"></span><span><?= e($ui['cta_bar']['contact'] ?? 'Contactez-nous') ?></span></a>
    <div class="rv-cta__w">
      <div class="rv-cta__glow"></div>
      <a class="btn btn-y rv-cta__d" href="<?= e($devis) ?>"><span class="fx"></span><span><?= e($ui['cta_bar']['devis'] ?? 'Demandez un devis') ?></span></a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($bot['enabled'])): ?>
<div class="rv-bot" id="rv-bot">
  <div class="rv-teaser" id="rv-teaser" hidden role="dialog" aria-label="<?= e($teaser['title'] ?? '') ?>">
    <button class="rv-teaser__x" type="button" data-act="teaser-close" aria-label="Fermer">×</button>
    <div class="rv-teaser__t"><?= e($teaser['title'] ?? '') ?></div>
    <div class="rv-teaser__p"><?= e($teaser['text'] ?? '') ?></div>
    <div class="rv-teaser__b">
      <a class="btn btn-y" href="<?= e($devis) ?>?etape=3" data-act="teaser-call"><span class="fx"></span><span><?= e($teaser['call'] ?? 'Rappel gratuit') ?></span></a>
      <button class="rv-teaser__chat" type="button" data-act="teaser-chat"><?= e($teaser['chat'] ?? 'Discuter') ?></button>
    </div>
  </div>
  <div class="rv-botp" id="rv-botp" hidden role="dialog" aria-label="<?= e($bot['name'] ?? 'Assistant') ?>">
    <div class="rv-botp__h">
      <div class="rv-botp__av">RE</div>
      <div>
        <div class="rv-botp__n"><?= e($bot['name'] ?? '') ?></div>
        <div class="rv-botp__s"><?= e($bot['status'] ?? '') ?></div>
      </div>
      <button class="rv-botp__x" type="button" data-act="bot-toggle" aria-label="Fermer">×</button>
    </div>
    <div class="rv-botp__m" id="rv-msgs" aria-live="polite"></div>
    <div class="rv-botp__q" id="rv-quick">
      <?php foreach (($bot['quick'] ?? []) as $i => $q): ?>
        <button class="qr" type="button" data-quick="<?= (int) $i ?>"><span class="fx"></span><span><?= e($q['q']) ?></span></button>
      <?php endforeach; ?>
      <a class="qd" href="<?= e($devis) ?>"><?= e($bot['devis'] ?? 'Demander un devis →') ?></a>
    </div>
    <form class="rv-botp__f" id="rv-botf" autocomplete="off">
      <input type="text" name="q" maxlength="600" placeholder="<?= e($bot['placeholder'] ?? '') ?>" aria-label="Votre message">
      <button type="submit" aria-label="Envoyer">→</button>
    </form>
  </div>
  <button class="rv-botl" id="rv-botl" type="button" data-act="bot-toggle" aria-label="Ouvrir l'assistant" aria-expanded="false">
    <span class="rv-botl__c"><span><i></i><i></i><i></i></span></span>
    <span class="rv-botl__x"><i></i><i></i></span>
  </button>
</div>
<?php endif; ?>

<?php if (!empty($exit['enabled'])): ?>
<div class="rv-exit" id="rv-exit" hidden role="dialog" aria-modal="true" aria-labelledby="rv-exit-t">
  <div class="rv-exit__c">
    <div class="rv-exit__img"><?php if (!empty($exit['image'])): ?><img src="<?= e($exit['image']) ?>" alt="" loading="lazy"><?php endif; ?></div>
    <div class="rv-exit__b">
      <button class="rv-exit__x" type="button" data-act="exit-close" aria-label="Fermer">×</button>
      <div class="rv-exit__e"><?= e($exit['eyebrow'] ?? '') ?></div>
      <div class="rv-exit__t" id="rv-exit-t"><?= e($exit['title'] ?? '') ?></div>
      <p class="rv-exit__p"><?= e($exit['text'] ?? '') ?></p>
      <form class="rv-exit__f" id="rv-exitf" action="<?= e($devis) ?>" method="get">
        <input type="hidden" name="etape" value="3">
        <input type="tel" name="tel" placeholder="<?= e($exit['placeholder'] ?? '') ?>" aria-label="Votre numéro de téléphone" autocomplete="tel">
        <button class="btn btn-y" type="submit"><span class="fx"></span><span><?= e($exit['button'] ?? 'Rappelez-moi') ?></span></button>
      </form>
      <button class="rv-exit__no" type="button" data-act="exit-close"><?= e($exit['dismiss'] ?? '') ?></button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($ui['cursor'])): ?><div class="rv-cursor" id="rv-cursor" aria-hidden="true"></div><?php endif; ?>
