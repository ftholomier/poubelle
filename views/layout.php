<?php

use App\View;

/** @var array<string,mixed> $site */
/** @var string $content */

$theme = $site['theme'] ?? [];
$meta = $site['meta'] ?? [];
?>
<!DOCTYPE html>
<html lang="<?= View::e($site['lang'] ?? 'fr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($meta['title'] ?? $site['name'] ?? 'Site') ?></title>
    <meta name="description" content="<?= View::e($meta['description'] ?? '') ?>">
    <?php if (!empty($meta['author'])): ?>
        <meta name="author" content="<?= View::e($meta['author']) ?>">
    <?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= View::e($meta['title'] ?? '') ?>">
    <meta property="og:description" content="<?= View::e($meta['description'] ?? '') ?>">
    <meta name="theme-color" content="<?= View::e($theme['background'] ?? '#08080f') ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= View::e(View::asset('assets/css/app.css')) ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><circle cx='16' cy='16' r='12' fill='%237b01f7'/></svg>">

    <style>
        :root {
            --bg: <?= View::e($theme['background'] ?? '#08080f') ?>;
            --fg: <?= View::e($theme['foreground'] ?? '#f4f2ff') ?>;
            --muted: <?= View::e($theme['muted'] ?? '#8d89a8') ?>;
            --accent: <?= View::e($theme['accent'] ?? '#7b01f7') ?>;
            --accent-2: <?= View::e($theme['accent2'] ?? '#c001f7') ?>;
            --accent-3: <?= View::e($theme['accent3'] ?? '#0089f7') ?>;
        }
    </style>
</head>
<body>
    <a class="skip-link" href="#contenu">Aller au contenu principal</a>

    <canvas id="particles" aria-hidden="true"></canvas>
    <div class="backdrop" aria-hidden="true"></div>

    <header class="masthead">
        <a class="masthead__brand" href="#hero">
            <span class="masthead__dot" aria-hidden="true"></span>
            <?= View::e($site['name'] ?? '') ?>
        </a>

        <button
            class="masthead__toggle"
            type="button"
            data-nav-toggle
            aria-expanded="false"
            aria-controls="menu-principal">
            <span class="masthead__toggle-label">Menu</span>
            <span class="masthead__burger" aria-hidden="true"><i></i><i></i></span>
        </button>

        <nav class="menu" id="menu-principal" data-nav-panel aria-label="Navigation principale">
            <ul class="menu__list">
                <?php foreach (($site['nav'] ?? []) as $item): ?>
                    <li>
                        <a
                            class="menu__link"
                            href="#<?= View::e($item['target']) ?>"
                            data-nav-link="<?= View::e($item['target']) ?>">
                            <?= View::e($item['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </header>

    <div id="smooth-wrapper">
        <div id="smooth-content">
            <main id="contenu"><?= $content ?></main>

            <footer class="footer">
                <div class="shell">
                    <p class="footer__line"><?= View::e($site['footer']['line'] ?? '') ?></p>
                    <p class="footer__legal">
                        <?= View::e($site['footer']['legal'] ?? '') ?>
                        <?php foreach (($site['footer']['links'] ?? []) as $link): ?>
                            · <a href="<?= View::e($link['href']) ?>" rel="noopener"><?= View::e($link['label']) ?></a>
                        <?php endforeach; ?>
                    </p>
                    <p class="footer__lab">
                        <a href="/labo">Laboratoire de formes</a>
                        · <a href="/api">API</a>
                    </p>
                </div>
            </footer>
        </div>
    </div>

    <div class="progress" data-progress aria-hidden="true"><span></span></div>
    <p class="shape-badge" data-shape-label aria-live="polite"></p>

    <script type="application/json" id="theme-data"><?= View::json($theme) ?></script>
    <script type="application/json" id="shapes-data"><?= View::json($shapesData ?? new stdClass()) ?></script>
    <script type="module" src="<?= View::e(View::asset('assets/js/main.js')) ?>"></script>
</body>
</html>
