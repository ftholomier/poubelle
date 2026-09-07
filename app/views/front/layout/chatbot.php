<?php
/**
 * Assistant IA (Gemini) — bulle en bas à droite, icône plate et compacte.
 * @var array $settings
 * @var string $lang
 */

use App\Content\Settings;

if (!Settings::bool('chatbot.enabled', true)) {
    return;
}
$suggestions = [];
foreach (Settings::arr('chatbot.suggestions') as $suggestion) {
    $text = trRaw($suggestion);
    if ($text !== '') {
        $suggestions[] = $text;
    }
}
?>
<div class="chatbot" data-chatbot>

    <button type="button" class="chatbot__launcher" data-chat-open
            aria-expanded="false" aria-controls="chatbot-panel">
        <span class="chatbot__launcher-icon" aria-hidden="true"><?= icon('chat', '', 22) ?></span>
        <span class="chatbot__launcher-label"><?= __e('chat.launcher') ?></span>
        <span class="chatbot__pulse" aria-hidden="true"></span>
    </button>

    <section class="chatbot__panel" id="chatbot-panel" data-chat-panel hidden
             aria-label="<?= __e('chat.title') ?>">

        <header class="chatbot__header">
            <span class="chatbot__avatar" aria-hidden="true"><?= icon('sparkles', '', 18) ?></span>
            <span class="chatbot__identity">
                <strong class="chatbot__name"><?= tr(Settings::get('chatbot.name')) ?></strong>
                <span class="chatbot__status"><span class="chatbot__dot" aria-hidden="true"></span>En ligne</span>
            </span>
            <button type="button" class="chatbot__close" data-chat-close aria-label="<?= __e('nav.close') ?>">
                <?= icon('close', '', 18) ?>
            </button>
        </header>

        <div class="chatbot__log" data-chat-log role="log" aria-live="polite" aria-atomic="false">
            <div class="chat-msg chat-msg--bot">
                <div class="chat-msg__bubble"><?= tr(Settings::get('chatbot.welcome')) ?></div>
            </div>
        </div>

        <?php if ($suggestions): ?>
            <div class="chatbot__suggestions" data-chat-suggestions>
                <?php foreach ($suggestions as $suggestion): ?>
                    <button type="button" class="chip" data-chat-suggestion><?= e($suggestion) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="chatbot__form" data-chat-form autocomplete="off">
            <label class="sr-only" for="chat-input"><?= __e('chat.placeholder') ?></label>
            <input class="chatbot__input" id="chat-input" name="message" type="text"
                   placeholder="<?= e(trRaw(Settings::get('chatbot.placeholder'))) ?>"
                   maxlength="1000" required>
            <button type="submit" class="chatbot__send" aria-label="<?= __e('chat.send') ?>">
                <?= icon('send', '', 18) ?>
            </button>
        </form>

        <footer class="chatbot__footer">
            <p class="chatbot__disclaimer"><?= __e('chat.disclaimer') ?></p>
            <a class="chatbot__handoff" href="<?= e(u((string) Settings::get('cta.coaching.url', '/contact'))) ?>">
                <?= tr(Settings::get('chatbot.handoff_label')) ?> <?= icon('arrow-right', '', 14) ?>
            </a>
        </footer>
    </section>
</div>
