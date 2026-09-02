<?php

use App\Http\PageController;
use App\View;

/** @var array<string,mixed> $site */
/** @var array<string,mixed> $page */
/** @var list<array{label:string,url:string,slug:string}> $navigation */
/** @var string $content */

$theme = $site['theme'] ?? [];
$meta = $site['meta'] ?? [];
$description = (string) ($page['meta']['description'] ?? $meta['description'] ?? '');
$title = PageController::documentTitle($site, $page);
$current = (string) ($page['slug'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= View::e($site['lang'] ?? 'fr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($title) ?></title>
    <meta name="description" content="<?= View::e($description) ?>">
    <?php if (!empty($meta['author'])): ?>
        <meta name="author" content="<?= View::e($meta['author']) ?>">
    <?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= View::e($title) ?>">
    <meta property="og:description" content="<?= View::e($description) ?>">
    <meta name="theme-color" content="<?= View::e($theme['background']) ?>">

    <?php /* Police servie par le site lui-même : aucun appel à un tiers, et le
             texte ne change plus d'apparence après le premier affichage. */ ?>
    <link rel="preload" as="font" type="font/woff2" crossorigin
          href="<?= View::e(View::asset('assets/fonts/montserrat-900.woff2')) ?>">
    <link rel="stylesheet" href="<?= View::e(View::asset('assets/css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= View::e(View::asset('assets/css/app.css')) ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><circle cx='16' cy='16' r='12' fill='<?= View::e(rawurlencode($theme['accent'])) ?>'/></svg>">

    <?php /*
      Les animations d'apparition masquent le texte en attendant d'être jouées.
      Elles ne doivent donc s'armer que si le script est réellement là : cette
      classe est posée tout de suite pour éviter un clignotement, puis retirée
      d'office si le module n'a pas démarré. Sans ce garde-fou, une erreur de
      chargement — type MIME refusé, navigateur trop ancien, fichier absent —
      laisserait une page à moitié vide au lieu d'un site simplement figé.
    */ ?>
    <script>
        (function () {
            var root = document.documentElement;
            root.classList.add('js');
            window.__revealGuard = setTimeout(function () {
                root.classList.remove('js');
                root.classList.add('js-failed');
            }, 6000);
        })();
    </script>

    <?php /* Toute la charte descend d'une seule couleur, dérivée côté serveur. */ ?>
    <style>
        :root {
            --bg: <?= View::e($theme['background']) ?>;
            --surface: <?= View::e($theme['surface']) ?>;
            --fg: <?= View::e($theme['foreground']) ?>;
            --fg-soft: <?= View::e($theme['foregroundSoft']) ?>;
            --fg-dim: <?= View::e($theme['foregroundDim']) ?>;
            --muted: <?= View::e($theme['muted']) ?>;
            --accent: <?= View::e($theme['accent']) ?>;
            --accent-2: <?= View::e($theme['accent2']) ?>;
            --accent-3: <?= View::e($theme['accent3']) ?>;
            --line: <?= View::e($theme['line']) ?>;
            --line-strong: <?= View::e($theme['lineStrong']) ?>;
            --veil: <?= View::e($theme['veil']) ?>;
            --glow-a: <?= View::e($theme['glowA']) ?>;
            --glow-b: <?= View::e($theme['glowB']) ?>;
            --shadow: <?= View::e($theme['shadow']) ?>;
        }
    </style>
</head>
<body>
    <a class="skip-link" href="#contenu">Aller au contenu principal</a>

    <canvas id="particles" aria-hidden="true"></canvas>
    <div class="backdrop" aria-hidden="true"></div>
    <div class="grain" aria-hidden="true"></div>

    <header class="masthead">
        <a class="masthead__brand" href="/" data-internal>
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
                <?php foreach ($navigation as $item): ?>
                    <li>
                        <a
                            class="menu__link<?= $item['slug'] === $current ? ' is-active' : '' ?>"
                            href="<?= View::e($item['url']) ?>"
                            data-internal
                            data-nav-link="<?= View::e($item['slug']) ?>"
                            <?= $item['slug'] === $current ? 'aria-current="page"' : '' ?>>
                            <?= View::e($item['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </header>

    <div id="smooth-wrapper">
        <div id="smooth-content">
            <main id="contenu" data-page="<?= View::e($current) ?>"><?= $content ?></main>

            <footer class="footer">
                <div class="shell">
                    <p class="footer__line"><?= View::e($site['footer']['line'] ?? '') ?></p>

                    <nav class="footer__nav" aria-label="Pages du site">
                        <?php foreach ($navigation as $item): ?>
                            <a href="<?= View::e($item['url']) ?>" data-internal><?= View::e($item['label']) ?></a>
                        <?php endforeach; ?>
                    </nav>

                    <p class="footer__legal">
                        <?= View::e($site['footer']['legal'] ?? '') ?>
                        <?php foreach (($site['footer']['links'] ?? []) as $link): ?>
                            · <a href="<?= View::e($link['href']) ?>" rel="noopener"><?= View::e($link['label']) ?></a>
                        <?php endforeach; ?>
                    </p>
                </div>
            </footer>
        </div>
    </div>

    <div class="progress" data-progress aria-hidden="true"><span></span></div>
    <p class="shape-badge" data-shape-label aria-live="polite"></p>
    <div class="page-veil" data-page-veil aria-hidden="true"></div>

    <script type="application/json" id="theme-data"><?= View::json($theme) ?></script>
    <script type="application/json" id="shapes-data"><?= View::json($shapesData ?: new stdClass()) ?></script>
    <script type="module" src="<?= View::e(View::asset('assets/js/main.js')) ?>"></script>
</body>
</html>
