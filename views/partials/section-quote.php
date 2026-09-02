<?php use App\View; ?>
<section class="section section--center" id="<?= View::e($section['id']) ?>" data-section>
    <div class="shell">
        <p class="eyebrow" data-reveal><?= View::e($section['eyebrow'] ?? '') ?></p>
        <blockquote class="quote">
            <?= View::capture('partials/title', [
                'lines' => (array) ($section['quote'] ?? []),
                'outlineFrom' => $section['outlineFrom'] ?? null,
            ]) ?>
        </blockquote>
        <p class="quote__author" data-reveal>
            <strong><?= View::e($section['author'] ?? '') ?></strong>
            <span><?= View::e($section['role'] ?? '') ?></span>
        </p>
    </div>
</section>
