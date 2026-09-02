<?php

use App\View;

/**
 * Bandeau de texte défilant, à la manière des sites d'agence : le texte alterne
 * plein et contour, et sa vitesse suit celle du défilement de la page.
 */

$text = (string) ($section['text'] ?? '');
$repeat = max(2, min((int) ($section['repeat'] ?? 4), 8));
?>
<section class="section section--marquee" id="<?= View::e($section['id']) ?>" data-section>
    <div
        class="marquee"
        data-marquee
        data-speed="<?= View::e((string) ($section['speed'] ?? 0.5)) ?>"
        aria-label="<?= View::e($text) ?>">
        <?php /* Deux pistes identiques défilent à la suite : la boucle est invisible. */ ?>
        <?php for ($track = 0; $track < 2; $track++): ?>
            <div class="marquee__track" <?= $track === 1 ? 'aria-hidden="true"' : '' ?>>
                <?php for ($i = 0; $i < $repeat; $i++): ?>
                    <span class="marquee__item<?= $i % 2 ? ' marquee__item--outline' : '' ?>">
                        <?= View::e($text) ?>
                    </span>
                <?php endfor; ?>
            </div>
        <?php endfor; ?>
    </div>

    <?php if (!empty($section['body'])): ?>
        <div class="shell">
            <p class="section__body section__body--center" data-reveal><?= View::e($section['body']) ?></p>
        </div>
    <?php endif; ?>
</section>
