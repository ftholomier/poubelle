<?php
/** @var array $g @var array $s */
$d = $g['dock'];
$avatar = site_avatar();
?>
<div class="dock" id="dock">
  <section class="dpanel" id="dock-chat" role="dialog" aria-label="<?= e($d['assistant_name']) ?>" hidden>
    <div class="dpanel__head">
      <div class="dpanel__deco" aria-hidden="true"></div>
      <div class="dav dav--44"><span aria-hidden="true">F</span><img src="<?= e($avatar) ?>" alt="" width="44" height="44" loading="lazy"></div>
      <div class="dpanel__who">
        <span class="dpanel__name"><?= t($d['assistant_name']) ?></span>
        <span class="dpanel__status"><i aria-hidden="true"></i><?= t($d['assistant_status']) ?></span>
      </div>
      <button class="dpanel__close" type="button" data-dock-close aria-label="Fermer"><?= icon('close', 14, '#fff') ?></button>
    </div>
    <div class="dpanel__list" id="dock-list" aria-live="polite">
      <div class="msg msg--bot"><div class="msg__b"><?= tp($d['greeting']) ?></div></div>
      <div class="dsugg" id="dock-sugg">
        <?php foreach ($d['suggestions'] ?? [] as $sug): ?><button type="button" data-ask="<?= e($sug) ?>"><?= t($sug) ?></button><?php endforeach; ?>
      </div>
    </div>
    <button class="dbrief" type="button" id="dock-brief" hidden><?= t($d['brief_button']) ?></button>
    <form class="dpanel__form" id="dock-form" autocomplete="off">
      <label class="sr-only" for="dock-input">Ton message</label>
      <input id="dock-input" name="q" maxlength="1500" placeholder="<?= e($d['input_placeholder']) ?>">
      <button type="button" class="dpanel__mic" data-voice-start aria-label="Laisser un vocal à Fred" title="Laisse un vocal à Fred"><?= icon('mic', 18, '#cc1517') ?></button>
      <button type="submit" class="dpanel__send" aria-label="Envoyer"><?= icon('send', 18, '#fff', 2.2) ?></button>
    </form>
  </section>

  <section class="vpanel" id="dock-voice" role="dialog" aria-label="<?= e($d['voice_title']) ?>" hidden>
    <div class="vpanel__head">
      <span class="vpanel__title" id="voice-title"><?= t($d['voice_title']) ?></span>
      <button class="vpanel__close" type="button" data-voice-close aria-label="Fermer"><?= icon('close', 13, '#fff') ?></button>
    </div>
    <p class="vpanel__sent" id="voice-sent" hidden><?= t($d['voice_sent_text']) ?></p>
    <div class="vpanel__review" id="voice-review" hidden>
      <audio controls id="voice-audio" preload="metadata"></audio>
      <div class="vpanel__tr" id="voice-transcript"></div>
      <div class="vpanel__sum" id="voice-summary" hidden><span><?= t($d['voice_summary_title']) ?></span><span id="voice-summary-text"></span></div>
      <div class="vpanel__fields">
        <label class="sr-only" for="voice-name">Ton prénom</label>
        <input id="voice-name" maxlength="80" placeholder="Ton prénom" autocomplete="given-name">
        <label class="sr-only" for="voice-phone">Ton téléphone</label>
        <input id="voice-phone" type="tel" maxlength="30" placeholder="Ton téléphone" autocomplete="tel">
      </div>
      <p class="vpanel__err" id="voice-form-err" hidden></p>
      <div class="vpanel__actions">
        <button type="button" class="vpanel__again" data-voice-start><?= t($d['voice_restart']) ?></button>
        <button type="button" class="vpanel__send" id="voice-send"><?= t($d['voice_send']) ?></button>
      </div>
    </div>
    <p class="vpanel__error" id="voice-error" hidden><?= t($d['voice_error_text']) ?></p>
  </section>

  <div class="dtoast" id="dock-toast" hidden><span class="dtoast__ic" aria-hidden="true">☕</span><span id="dock-toast-text"></span></div>

  <div class="dbar-wrap">
    <div class="dbar" id="dock-bar">
      <div class="dbar__rec" id="dock-rec" hidden>
        <button type="button" class="dbar__stop" data-voice-stop aria-label="Arrêter l'enregistrement"><span></span></button>
        <span class="dbar__time" id="voice-time">0:00</span>
        <div class="dbar__bars" id="voice-bars" aria-hidden="true"><?= str_repeat('<span></span>', 28) ?></div>
        <span class="dbar__q" id="voice-question"><?= t($d['voice_questions'][0] ?? '') ?></span>
        <button type="button" class="dbar__finish" data-voice-stop><?= t($d['voice_finish']) ?></button>
        <button type="button" class="dbar__cancel" data-voice-cancel aria-label="Annuler"><?= icon('close', 12, '#fff') ?></button>
      </div>
      <div class="dbar__idle" id="dock-idle">
        <span class="dbar__av" aria-hidden="true">
          <span>F</span><img src="<?= e($avatar) ?>" alt="" width="50" height="50" loading="lazy"><i></i>
        </span>
        <span class="dbar__who"><b><?= t($d['name']) ?></b><span id="dock-status"><?= t($d['status_open']) ?></span></span>
        <button type="button" class="dbar__voice" data-voice-start title="Laisse un vocal à Fred">
          <span class="dbar__rec-ic" aria-hidden="true"><?= icon('mic', 21, '#fff', 2.3) ?></span><span class="dbar__lbl"><?= t($d['voice_label']) ?></span>
        </button>
        <a class="dbar__rdv" href="/on-prend-rdv"><?= t($d['rdv_label']) ?></a>
        <?php if (phone_label() !== ''): ?>
          <a class="dbar__tel" href="<?= e(tel_href()) ?>" aria-label="Appeler Frédéric"><?= icon('phone', 19, '#161922') ?></a>
        <?php else: ?>
          <a class="dbar__tel" href="/on-prend-rdv#rappel" aria-label="Être rappelé par Frédéric"><?= icon('phone', 19, '#161922') ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <button type="button" class="dfab" data-dock-toggle aria-controls="dock-chat" aria-expanded="false" aria-label="Demande à l’assistant IA de Fred" title="Demande à l’IA">
    <span class="dfab__ic dfab__ic--open" aria-hidden="true"><?= icon('spark', 26, '#fff', 1.8) ?></span>
    <span class="dfab__ic dfab__ic--close" aria-hidden="true"><?= icon('close', 18, '#fff', 2.4) ?></span>
    <span class="dfab__badge" aria-hidden="true"><?= t($d['ia_label']) ?></span>
  </button>
</div>
