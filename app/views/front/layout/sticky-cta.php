<?php
/**
 * Barre d'appel à l'action collée au bas de l'écran, centrée,
 * présente sur toutes les pages.
 * @var array $settings
 */

use App\Content\Settings;

if (!Settings::bool('cta.sticky_enabled', true)) {
    return;
}
$note = trRaw(Settings::get('cta.note'));
?>
<div class="sticky-cta" data-sticky-cta role="complementary" aria-label="Actions rapides">
    <div class="sticky-cta__inner">

        <a class="btn btn--ghost btn--slide sticky-cta__btn"
           href="<?= e(u((string) Settings::get('cta.contact.url', '/contact'))) ?>"
           data-cta="contact">
            <?= icon('mail', 'btn__icon', 18) ?>
            <span class="btn__label"><?= tr(Settings::get('cta.contact.label')) ?></span>
        </a>

        <a class="btn btn--accent btn--slide btn--halo sticky-cta__btn sticky-cta__btn--primary"
           href="<?= e(u((string) Settings::get('cta.coaching.url', '/contact'))) ?>"
           data-cta="coaching">
            <span class="btn__halo" aria-hidden="true"></span>
            <?= icon('spark', 'btn__icon', 18) ?>
            <span class="btn__label"><?= tr(Settings::get('cta.coaching.label')) ?></span>
        </a>

        <?php if ($note !== ''): ?>
            <p class="sticky-cta__note"><?= e($note) ?></p>
        <?php endif; ?>
    </div>
</div>
