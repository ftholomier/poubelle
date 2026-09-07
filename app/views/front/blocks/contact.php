<?php
/** @var array $block @var array $data @var array $settings @var string $lang */

use App\Content\Settings;
use App\Security\Csrf;

$site = $settings['site'] ?? [];
$subjects = Settings::arr('forms.subjects');
$preselected = isset($_GET['sujet']) ? preg_replace('/[^a-z\-]/', '', (string) $_GET['sujet']) : '';
$mapQuery = (string) ($site['map_query'] ?? '');
?>
<section class="section section--<?= e($block['theme']) ?> section--contact"
    <?= !empty($block['anchor']) ? 'id="' . e($block['anchor']) . '"' : ' id="contact"' ?>>
    <div class="shell">
        <header class="section__head">
            <?php if (trRaw($data['eyebrow']) !== ''): ?>
                <p class="eyebrow" <?= reveal('up') ?>><span class="eyebrow__dot" aria-hidden="true"></span><?= tr($data['eyebrow']) ?></p>
            <?php endif; ?>
            <?php if (trRaw($data['title']) !== ''): ?>
                <h2 class="section__title" <?= reveal('up', 80) ?>><?= tr($data['title']) ?></h2>
            <?php endif; ?>
            <?php if (trRaw($data['text']) !== ''): ?>
                <p class="section__text" <?= reveal('up', 130) ?>><?= tr($data['text']) ?></p>
            <?php endif; ?>
        </header>

        <div class="contact">
            <form class="contact__form" data-contact-form novalidate <?= reveal('right') ?>>
                <input type="hidden" name="_token" value="<?= e(Csrf::token('public')) ?>">
                <input type="hidden" name="lang" value="<?= e($lang) ?>">
                <input type="hidden" name="started_at" value="<?= time() ?>">
                <input type="hidden" name="page" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
                <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

                <div class="field-row">
                    <p class="field">
                        <label class="field__label" for="c-name"><?= __e('form.name') ?> <span aria-hidden="true">*</span></label>
                        <input class="field__input" id="c-name" name="name" type="text" required
                               autocomplete="name" maxlength="120">
                        <span class="field__error" data-error-for="name"></span>
                    </p>
                    <p class="field">
                        <label class="field__label" for="c-email"><?= __e('form.email') ?> <span aria-hidden="true">*</span></label>
                        <input class="field__input" id="c-email" name="email" type="email" required
                               autocomplete="email" maxlength="180">
                        <span class="field__error" data-error-for="email"></span>
                    </p>
                </div>

                <div class="field-row">
                    <p class="field">
                        <label class="field__label" for="c-phone"><?= __e('form.phone') ?></label>
                        <input class="field__input" id="c-phone" name="phone" type="tel"
                               autocomplete="tel" maxlength="40">
                    </p>
                    <p class="field">
                        <label class="field__label" for="c-company"><?= __e('form.company') ?></label>
                        <input class="field__input" id="c-company" name="company" type="text"
                               autocomplete="organization" maxlength="140">
                    </p>
                </div>

                <?php if ($subjects): ?>
                    <p class="field">
                        <label class="field__label" for="c-subject"><?= __e('form.subject') ?></label>
                        <span class="field__select">
                            <select class="field__input" id="c-subject" name="subject">
                                <?php foreach ($subjects as $subject):
                                    $value = (string) ($subject['value'] ?? '');
                                    ?>
                                    <option value="<?= e($value) ?>"<?= $value === $preselected ? ' selected' : '' ?>>
                                        <?= tr($subject['label'] ?? $value) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </span>
                    </p>
                <?php endif; ?>

                <p class="field">
                    <label class="field__label" for="c-message"><?= __e('form.message') ?> <span aria-hidden="true">*</span></label>
                    <textarea class="field__input field__input--area" id="c-message" name="message"
                              rows="5" required maxlength="4000"></textarea>
                    <span class="field__error" data-error-for="message"></span>
                </p>

                <p class="field field--check">
                    <label class="check">
                        <input class="check__input" type="checkbox" name="consent" value="1" required>
                        <span class="check__box" aria-hidden="true"><?= icon('check', '', 14) ?></span>
                        <span class="check__label"><?= __e('form.consent') ?></span>
                    </label>
                    <span class="field__error" data-error-for="consent"></span>
                </p>

                <button type="submit" class="btn btn--accent btn--slide btn--halo btn--block btn--lg">
                    <span class="btn__halo" aria-hidden="true"></span>
                    <span class="btn__label"><?= __e('form.send') ?></span>
                    <?= icon('arrow-right', 'btn__icon btn__icon--end', 18) ?>
                </button>

                <p class="contact__feedback" data-form-feedback role="status" aria-live="polite"></p>
            </form>

            <?php if (!empty($data['show_info'])): ?>
                <aside class="contact__info" <?= reveal('left', 120) ?>>
                    <div class="contact__card">
                        <h3 class="contact__card-title"><?= e((string) ($settings['legal']['director'] ?? '')) ?></h3>
                        <p class="contact__card-role"><?= tr($site['tagline'] ?? '') ?></p>

                        <ul class="contact-list contact-list--boxed">
                            <?php if (!empty($site['phone'])): ?>
                                <li>
                                    <?= icon('phone', 'contact-list__icon', 18) ?>
                                    <a class="link-underline" href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $site['phone'])) ?>">
                                        <span class="link-underline__text"><?= e((string) ($site['phone_display'] ?: $site['phone'])) ?></span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li>
                                <?= icon('mail', 'contact-list__icon', 18) ?>
                                <a class="link-underline" href="mailto:<?= e((string) $site['email']) ?>">
                                    <span class="link-underline__text"><?= e((string) $site['email']) ?></span>
                                </a>
                            </li>
                            <li>
                                <?= icon('pin', 'contact-list__icon', 18) ?>
                                <span><?= e((string) $site['address']) ?><br><?= e((string) $site['zip']) ?> <?= e((string) $site['city']) ?></span>
                            </li>
                            <li>
                                <?= icon('clock', 'contact-list__icon', 18) ?>
                                <span><?= tr($site['hours'] ?? '') ?></span>
                            </li>
                        </ul>

                        <a class="btn btn--primary btn--slide btn--block" href="<?= e(u((string) Settings::get('cta.coaching.url', '/contact'))) ?>">
                            <span class="btn__label"><?= tr(Settings::get('cta.coaching.label')) ?></span>
                        </a>
                    </div>

                    <?php if (!empty($data['show_map']) && $mapQuery !== ''): ?>
                        <div class="contact__map">
                            <iframe
                                title="Localisation du cabinet"
                                src="https://www.google.com/maps?q=<?= e(rawurlencode($mapQuery)) ?>&output=embed"
                                width="100%" height="260" style="border:0" loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen></iframe>
                            <?php /* Repli visible si la carte ne peut pas se charger. */ ?>
                            <a class="contact__map-link"
                               href="https://www.google.com/maps/search/?api=1&query=<?= e(rawurlencode($mapQuery)) ?>"
                               target="_blank" rel="noopener noreferrer">
                                <?= icon('pin', 'contact-list__icon', 16) ?>
                                <span class="link-underline__text"><?= e($mapQuery) ?></span>
                            </a>
                        </div>
                    <?php endif; ?>
                </aside>
            <?php endif; ?>
        </div>
    </div>
</section>
