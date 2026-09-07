<?php
/**
 * Pied de page : navigation avec soulignement animé de gauche à droite.
 * @var array $settings
 * @var array $menu
 * @var array $footerNav
 */

use App\Content\Settings;

$site   = $settings['site'] ?? [];
$social = array_filter($settings['social'] ?? [], static fn ($url): bool => is_string($url) && trim($url) !== '');
$homeSlug = App\Content\Pages::homeSlug();
?>
<footer class="site-footer">
    <div class="shell">

        <div class="site-footer__top">
            <div class="site-footer__brand" <?= reveal('up') ?>>
                <a class="brand brand--footer" href="<?= e(u('/')) ?>">
                    <?php if (!empty($site['logo_light'])): ?>
                        <img class="brand__logo" src="<?= e(asset((string) $site['logo_light'])) ?>"
                             alt="<?= e((string) ($settings['legal']['director'] ?? '') . ' — ' . ($site['name'] ?? '')) ?>"
                             width="306" height="44" loading="lazy">
                    <?php else: ?>
                        <span class="brand__text">
                            <span class="brand__name"><?= e((string) ($settings['legal']['director'] ?? '')) ?></span>
                            <span class="brand__role"><?= e((string) ($site['name'] ?? '')) ?></span>
                        </span>
                    <?php endif; ?>
                </a>
                <p class="site-footer__tagline"><?= tr($site['tagline'] ?? '') ?></p>

                <?php if ($social): ?>
                    <ul class="social">
                        <?php foreach ($social as $network => $url): ?>
                            <li>
                                <a class="social__link" href="<?= e((string) $url) ?>" target="_blank"
                                   rel="noopener noreferrer" aria-label="<?= e(ucfirst((string) $network)) ?>">
                                    <?= icon((string) $network, 'social__icon', 20) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <nav class="site-footer__nav" aria-label="<?= __e('footer.navigation') ?>" <?= reveal('up', 80) ?>>
                <h2 class="site-footer__title"><?= __e('footer.navigation') ?></h2>
                <ul class="footer-menu">
                    <?php foreach ($menu as $item): ?>
                        <li>
                            <a class="footer-menu__link" href="<?= e($item['slug'] === $homeSlug ? u('/') : u('/' . $item['slug'])) ?>">
                                <span class="footer-menu__text"><?= tr($item['nav_label'] ?? $item['title']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="site-footer__contact" <?= reveal('up', 160) ?>>
                <h2 class="site-footer__title"><?= __e('footer.contact') ?></h2>
                <ul class="contact-list">
                    <?php if (!empty($site['address'])): ?>
                        <li>
                            <?= icon('pin', 'contact-list__icon', 18) ?>
                            <span><?= e((string) $site['address']) ?><br><?= e((string) $site['zip']) ?> <?= e((string) $site['city']) ?></span>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($site['phone'])): ?>
                        <li>
                            <?= icon('phone', 'contact-list__icon', 18) ?>
                            <a class="footer-menu__link" href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $site['phone'])) ?>">
                                <span class="footer-menu__text"><?= e((string) ($site['phone_display'] ?: $site['phone'])) ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($site['email'])): ?>
                        <li>
                            <?= icon('mail', 'contact-list__icon', 18) ?>
                            <a class="footer-menu__link" href="mailto:<?= e((string) $site['email']) ?>">
                                <span class="footer-menu__text"><?= e((string) $site['email']) ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($site['hours'])): ?>
                        <li>
                            <?= icon('clock', 'contact-list__icon', 18) ?>
                            <span><?= tr($site['hours']) ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="site-footer__bottom">
            <p class="site-footer__copy">
                © <?= date('Y') ?> <?= e((string) ($site['name'] ?? '')) ?> — <?= __e('footer.rights') ?>
            </p>
            <ul class="footer-legal">
                <?php foreach ($footerNav as $item): ?>
                    <li>
                        <a class="footer-menu__link" href="<?= e(u('/' . $item['slug'])) ?>">
                            <span class="footer-menu__text"><?= tr($item['nav_label'] ?? $item['title']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li>
                    <a class="footer-menu__link" href="/sitemap.xml">
                        <span class="footer-menu__text"><?= __e('footer.sitemap') ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <button type="button" class="to-top" data-to-top aria-label="<?= __e('a11y.top') ?>">
        <?= icon('arrow-up', '', 20) ?>
    </button>
</footer>
