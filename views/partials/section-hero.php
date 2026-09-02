<?php use App\View; ?>
<section class="section section--hero" id="<?= View::e($section['id']) ?>" data-section>
    <div class="shell">
        <p class="eyebrow" data-reveal><?= View::e($section['eyebrow'] ?? '') ?></p>
        <h1 class="title title--xl">
            <?php foreach (($section['title'] ?? []) as $line): ?>
                <span class="title__line" data-reveal="words"><?= View::e($line) ?></span>
            <?php endforeach; ?>
        </h1>
        <?php if (!empty($section['subtitle'])): ?>
            <p class="hero__subtitle" data-reveal><?= View::e($section['subtitle']) ?></p>
        <?php endif; ?>
        <?php if (!empty($section['body'])): ?>
            <p class="section__body section__body--lead" data-reveal><?= View::e($section['body']) ?></p>
        <?php endif; ?>
    </div>
    <p class="hero__scroll" aria-hidden="true"><span></span>Défiler</p>
</section>
