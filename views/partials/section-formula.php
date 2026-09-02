<?php use App\View; ?>
<section class="section section--center" id="<?= View::e($section['id']) ?>" data-section>
    <div class="shell">
        <p class="eyebrow" data-reveal><?= View::e($section['eyebrow'] ?? '') ?></p>
        <p class="formula" data-reveal="words"><?= View::e($section['formula'] ?? '') ?></p>
        <?php if (!empty($section['body'])): ?>
            <p class="section__body section__body--center" data-reveal><?= View::e($section['body']) ?></p>
        <?php endif; ?>
    </div>
</section>
