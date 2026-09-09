<?php
/** Assistant iOiO : lanceur + panneau (bas à droite, jamais plus haut). */

use App\Ai\Gemini;
use App\I18n;
use App\Text;

if (empty($settings['ai']['enabled'])) {
    return;
}
$lang = I18n::lang();
$suggestions = Gemini::suggestions($lang);
$poweredByGemini = Gemini::configured();
?>
<div class="bot" data-bot>
  <div class="bot__panel" data-bot-panel hidden role="dialog" aria-label="<?= Text::e(I18n::t('bot.title')) ?>">
    <div class="bot__head">
      <span class="bot__mark" aria-hidden="true"></span>
      <span style="flex:1">
        <span class="bot__title"><?= Text::e(I18n::t('bot.title')) ?></span>
        <span class="bot__sub"><?= Text::e(I18n::t('bot.sub')) ?></span>
      </span>
      <button class="bot__close" type="button" data-bot-close aria-label="<?= Text::e(I18n::t('bot.close')) ?>">×</button>
    </div>

    <div class="bot__log" data-bot-log aria-live="polite">
      <div class="bot__msg"><span><?= Text::e(Gemini::greeting($lang)) ?></span></div>
    </div>

    <div class="bot__typing" data-bot-typing hidden aria-hidden="true"><span></span><span></span><span></span></div>

    <div class="bot__suggestions">
      <?php foreach (\array_slice($suggestions, 0, 3) as $suggestion): ?>
        <button class="bot__suggestion" type="button" data-bot-suggestion><?= Text::e($suggestion) ?></button>
      <?php endforeach; ?>
    </div>

    <form class="bot__form" data-bot-form action="#" method="post">
      <label class="sr-only" for="bot-input"><?= Text::e(I18n::t('bot.placeholder')) ?></label>
      <input class="bot__input" id="bot-input" data-bot-input type="text" maxlength="500" autocomplete="off"
             placeholder="<?= Text::e(I18n::t('bot.placeholder')) ?>">
      <button class="bot__send" type="submit" aria-label="Envoyer">→</button>
    </form>

    <div class="bot__footer"><?= Text::e($poweredByGemini ? I18n::t('bot.footer') : I18n::t('bot.footerLocal')) ?></div>
  </div>

  <button class="bot__launcher" type="button" data-bot-launcher aria-expanded="false" data-track="chat_launcher">
    <span class="bot__launcherMark" aria-hidden="true"><span class="bot__launcherDot"></span></span>
    <span class="bot__launcherText">
      <span class="bot__launcherTitle"><?= Text::e(I18n::t('bot.cta')) ?></span>
      <span class="bot__launcherSub"><?= Text::e(I18n::t('bot.cta2')) ?></span>
    </span>
  </button>
</div>
