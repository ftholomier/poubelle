<?php $bot = Bot::config(); ?>
<div class="bot" id="bot"
     data-greeting="<?= e($bot['greeting']) ?>"
     data-endpoint="<?= e(url('api/bot/chat')) ?>">
  <button class="bot__toggle" type="button" aria-expanded="false" aria-controls="bot-panel"
          aria-label="Ouvrir l’assistant Suisse Immo">
    <span class="bot__toggle-icon"><?= icon('bot') ?></span>
    <span class="bot__toggle-close"><?= icon('close') ?></span>
    <span class="bot__pulse" aria-hidden="true"></span>
  </button>

  <div class="bot__panel" id="bot-panel" role="dialog" aria-label="Assistant Suisse Immo" hidden>
    <header class="bot__head">
      <span class="bot__avatar" aria-hidden="true"><?= icon('bot') ?></span>
      <span class="bot__id">
        <b><?= e($bot['name']) ?></b>
        <small><?= e($bot['role']) ?></small>
      </span>
      <span class="bot__status"><span class="dot"></span> en ligne</span>
      <button class="bot__close" type="button" aria-label="Fermer"><?= icon('close') ?></button>
    </header>

    <div class="bot__log" id="bot-log" role="log" aria-live="polite"></div>

    <?php if (!empty($bot['suggestions'])): ?>
      <div class="bot__chips" id="bot-chips">
        <?php foreach ((array) $bot['suggestions'] as $s): ?>
          <button class="bot__chip" type="button"><?= e($s) ?></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form class="bot__form" id="bot-form-front">
      <?= Csrf::field() ?>
      <label class="sr-only" for="bot-input">Votre question</label>
      <input class="bot__input" id="bot-input" name="question" autocomplete="off"
             placeholder="Posez votre question…" maxlength="500">
      <button class="bot__send" type="submit" aria-label="Envoyer"><?= icon('send') ?></button>
    </form>
    <p class="bot__legal">
      Réponses générées par IA à partir du contenu de ce site. En cas de doute,
      <a href="<?= e(url('contact')) ?>">écrivez-nous</a>.
    </p>
  </div>
</div>
