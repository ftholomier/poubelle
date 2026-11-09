<?php
$site = Content::site();
$ui = Content::ui();
$bot = $ui['bot'] ?? [];
$exit = $ui['exit'] ?? [];
$teaser = $ui['teaser'] ?? [];
$devis = Content::path('devis');
?>
<?php $voice = Voice::cfg(); $hasVoice = !empty($voice['enabled']); ?>
<?php if (!empty($ui['cta_bar']['enabled'])): ?>
<?php if ($hasVoice): ?>
<section class="rv-vpanel" id="rv-vpanel" role="dialog" aria-labelledby="rv-v-title" hidden>
  <div class="rv-vpanel__h">
    <span class="rv-vpanel__dot" aria-hidden="true"></span>
    <span class="rv-vpanel__t" id="rv-v-title"><?= e($voice['title']) ?></span>
    <button class="rv-vpanel__x" type="button" data-voice-close aria-label="Fermer">×</button>
  </div>
  <div class="rv-vpanel__b">
    <div id="rv-v-review" hidden>
      <audio controls preload="metadata" id="rv-v-audio"></audio>
      <div class="rv-vpanel__tr" id="rv-v-tr"></div>
      <div class="rv-vpanel__sum" id="rv-v-sum" hidden><b><?= e($voice['summary_label']) ?></b><span id="rv-v-sumtxt"></span></div>
      <div class="rv-vpanel__f">
        <label class="sr-only" for="rv-v-nom">Nom et prénom</label>
        <input id="rv-v-nom" maxlength="120" placeholder="Nom et prénom" autocomplete="name">
        <label class="sr-only" for="rv-v-tel">Téléphone</label>
        <input id="rv-v-tel" type="tel" maxlength="30" placeholder="Téléphone *" autocomplete="tel" required>
      </div>
      <p class="rv-vpanel__err" id="rv-v-err" hidden></p>
      <div class="rv-vpanel__a">
        <button class="btn btn-l" type="button" data-voice-start><span class="fx"></span><span><?= e($voice['again']) ?></span></button>
        <button class="btn btn-y" type="button" id="rv-v-send"><span class="fx"></span><span><?= e($voice['send']) ?></span></button>
      </div>
      <p class="rv-vpanel__legal"><?= e($ui['devis_form']['legal'] ?? '') ?></p>
    </div>
    <div class="rv-vpanel__ok" id="rv-v-sent" hidden><div class="dv__okc">✓</div><p><?= e($voice['sent_text']) ?></p></div>
    <p class="rv-vpanel__p" id="rv-v-error" hidden><?= e($voice['error_text']) ?></p>
  </div>
</section>
<?php endif; ?>
<div class="rv-cta<?= $hasVoice ? ' has-voice' : '' ?>" id="rv-cta">
  <div class="rv-cta__box">
    <?php if ($hasVoice): ?>
    <div class="rv-rec" id="rv-rec" hidden>
      <button class="rv-rec__stop" type="button" data-voice-stop aria-label="Arrêter l'enregistrement"><span></span></button>
      <span class="rv-rec__time" id="rv-v-time">0:00</span>
      <div class="rv-rec__bars" id="rv-v-bars" aria-hidden="true"><?= str_repeat('<span></span>', 28) ?></div>
      <span class="rv-rec__q" id="rv-v-q"><?= e($voice['questions'][0] ?? '') ?></span>
      <button class="rv-rec__fin" type="button" data-voice-stop><?= e($voice['finish']) ?></button>
      <button class="rv-rec__x" type="button" data-voice-cancel aria-label="Annuler l'enregistrement">×</button>
    </div>
    <button class="btn rv-cta__v" type="button" data-voice-start title="<?= e($voice['label']) ?>" aria-label="<?= e($voice['label']) ?>">
      <span class="fx"></span>
      <span class="rv-cta__vin"><span class="rv-cta__mic" aria-hidden="true"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0014 0M12 18v3"/></svg></span><span class="rv-cta__vl"><?= e($voice['label']) ?></span></span>
    </button>
    <?php endif; ?>
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
