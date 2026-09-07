<?php
/**
 * En-tête : logo, navigation, sélecteur de langue, CTA.
 * @var array $settings
 * @var array $menu
 * @var string $lang
 * @var array $languages
 * @var array|null $page
 */

use App\Content\Settings;
use App\I18n\Translator;

$site       = $settings['site'] ?? [];
$currentSlug = $page['slug'] ?? '';
$homeSlug   = App\Content\Pages::homeSlug();
$default    = Settings::str('i18n.default', 'fr');
$path       = ($currentSlug === '' || $currentSlug === $homeSlug) ? '/' : '/' . $currentSlug;
?>
<a class="skip-link" href="#contenu"><?= __e('skip.content') ?></a>

<header class="site-header" id="site-header" data-header>
    <div class="shell site-header__inner">

        <a class="brand" href="<?= e(u('/')) ?>" aria-label="<?= e((string) ($settings['legal']['director'] ?? '') . ' — ' . ($site['name'] ?? '')) ?>">
            <?php if (!empty($site['logo'])): ?>
                <img class="brand__logo" src="<?= e(asset((string) $site['logo'])) ?>"
                     alt="<?= e((string) ($settings['legal']['director'] ?? '') . ' — ' . ($site['name'] ?? '')) ?>"
                     width="278" height="40">
            <?php else: ?>
                <span class="brand__text">
                    <span class="brand__name"><?= e((string) ($settings['legal']['director'] ?? '')) ?></span>
                    <span class="brand__role"><?= e((string) ($site['name'] ?? '')) ?></span>
                </span>
            <?php endif; ?>
        </a>

        <nav class="nav" id="primary-nav" aria-label="Navigation principale">
            <ul class="nav__list">
                <?php foreach ($menu as $item):
                    $itemSlug = (string) $item['slug'];
                    $isActive = $itemSlug === $currentSlug;
                    $href = $itemSlug === $homeSlug ? u('/') : u('/' . $itemSlug);
                    ?>
                    <li class="nav__item">
                        <a class="nav__link<?= $isActive ? ' is-active' : '' ?>"
                           href="<?= e($href) ?>"
                            <?= $isActive ? ' aria-current="page"' : '' ?>>
                            <span class="nav__label"><?= tr($item['nav_label'] ?? $item['title']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="site-header__actions">

            <?php if (count($languages) > 1): ?>
                <div class="lang" data-lang-switcher>
                    <button type="button" class="lang__toggle" aria-expanded="false"
                            aria-controls="lang-menu" aria-label="<?= __e('lang.switch') ?>">
                        <?= icon('globe', 'lang__icon', 18) ?>
                        <span class="lang__current"><?= e(strtoupper($lang)) ?></span>
                    </button>
                    <ul class="lang__menu" id="lang-menu" hidden>
                        <?php foreach ($languages as $code): ?>
                            <li>
                                <a class="lang__option<?= $code === $lang ? ' is-active' : '' ?>"
                                   href="<?= e(($code === $default ? '' : '/' . $code) . ($path === '/' ? '/' : $path)) ?>"
                                   hreflang="<?= e($code) ?>"
                                   data-lang="<?= e($code) ?>">
                                    <span><?= e(Translator::label($code)) ?></span>
                                    <?php if ($code === $lang): ?><?= icon('check', 'lang__check', 16) ?><?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <a class="btn btn--primary btn--slide site-header__cta" href="<?= e(u((string) Settings::get('cta.contact.url', '/contact'))) ?>">
                <span class="btn__label"><?= tr(Settings::get('cta.contact.label')) ?></span>
            </a>

            <button type="button" class="burger" data-nav-toggle aria-expanded="false"
                    aria-controls="primary-nav" aria-label="<?= __e('nav.open') ?>">
                <span class="burger__bar"></span>
                <span class="burger__bar"></span>
                <span class="burger__bar"></span>
            </button>
        </div>
    </div>
    <div class="site-header__progress" data-scroll-progress aria-hidden="true"></div>
</header>

<div class="nav-overlay" data-nav-overlay hidden></div>
