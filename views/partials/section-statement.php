<?php use App\View; ?>
<section class="section section--split" id="<?= View::e($section['id']) ?>" data-section>
    <div class="shell shell--split">
        <p class="eyebrow eyebrow--sticky" data-reveal><?= View::e($section['eyebrow'] ?? '') ?></p>
        <div class="statement">
            <h2 class="title title--lg">
                <?php foreach ((array) ($section['title'] ?? []) as $line): ?>
                    <span class="title__line" data-reveal="words"><?= View::e($line) ?></span>
                <?php endforeach; ?>
            </h2>
            <?php if (!empty($section['body'])): ?>
                <p class="section__body" data-reveal><?= View::e($section['body']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>
