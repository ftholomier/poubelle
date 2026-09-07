<?php
/**
 * Fenêtre d'intention de sortie : dernière incitation à passer à l'action.
 * @var array $settings
 */

use App\Content\Settings;
use App\Security\Csrf;

if (!Settings::bool('exit_popup.enabled', true)) {
    return;
}
$popup = Settings::arr('exit_popup');
?>
<div class="exit-popup" data-exit-popup hidden
     data-delay="<?= (int) ($popup['delay_ms'] ?? 1200) ?>"
     data-frequency="<?= (int) ($popup['frequency_days'] ?? 7) ?>"
     data-mobile-timeout="<?= (int) ($popup['mobile_timeout'] ?? 45) ?>">

    <div class="exit-popup__backdrop" data-exit-close></div>

    <div class="exit-popup__dialog" role="dialog" aria-modal="true"
         aria-labelledby="exit-popup-title" tabindex="-1">

        <button type="button" class="exit-popup__close" data-exit-close aria-label="<?= __e('popup.close') ?>">
            <?= icon('close', '', 20) ?>
        </button>

        <span class="exit-popup__glyph" aria-hidden="true"><?= icon('glasses', '', 34) ?></span>

        <p class="exit-popup__eyebrow"><?= tr($popup['eyebrow'] ?? '') ?></p>
        <h2 class="exit-popup__title" id="exit-popup-title"><?= tr($popup['title'] ?? '') ?></h2>
        <p class="exit-popup__text"><?= tr($popup['text'] ?? '') ?></p>

        <?php if (!empty($popup['capture_email'])): ?>
            <form class="exit-popup__form" data-exit-form novalidate>
                <input type="hidden" name="_token" value="<?= e(Csrf::token('public')) ?>">
                <input type="hidden" name="source" value="exit_popup">
                <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

                <label class="sr-only" for="exit-email"><?= __e('form.email') ?></label>
                <input class="exit-popup__input" id="exit-email" type="email" name="email"
                       placeholder="<?= __e('form.email') ?>" required autocomplete="email">

                <button type="submit" class="btn btn--accent btn--slide btn--halo">
                    <span class="btn__halo" aria-hidden="true"></span>
                    <span class="btn__label"><?= tr($popup['cta_label'] ?? '') ?></span>
                </button>
                <p class="exit-popup__feedback" data-exit-feedback role="status"></p>
            </form>
        <?php else: ?>
            <a class="btn btn--accent btn--slide btn--halo" href="<?= e(u((string) ($popup['cta_url'] ?? '/contact'))) ?>">
                <span class="btn__halo" aria-hidden="true"></span>
                <span class="btn__label"><?= tr($popup['cta_label'] ?? '') ?></span>
            </a>
        <?php endif; ?>

        <button type="button" class="exit-popup__dismiss" data-exit-close>
            <?= tr($popup['dismiss'] ?? '') ?>
        </button>
    </div>
</div>
