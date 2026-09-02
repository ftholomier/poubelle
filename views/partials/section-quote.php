<?php use App\View; ?>
<section class="section section--center" id="<?= View::e($section['id']) ?>" data-section>
    <div class="shell">
        <p class="eyebrow" data-reveal><?= View::e($section['eyebrow'] ?? '') ?></p>
        <blockquote class="quote">
            <?php foreach ((array) ($section['quote'] ?? []) as $line): ?>
                <span class="title__line" data-reveal="words"><?= View::e($line) ?></span>
            <?php endforeach; ?>
        </blockquote>
        <p class="quote__author" data-reveal>
            <strong><?= View::e($section['author'] ?? '') ?></strong>
            <span><?= View::e($section['role'] ?? '') ?></span>
        </p>
    </div>
</section>
