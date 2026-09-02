<?php use App\View; ?>
<section class="section section--contact" id="<?= View::e($section['id']) ?>" data-section>
    <div class="shell">
        <p class="eyebrow" data-reveal><?= View::e($section['eyebrow'] ?? '') ?></p>
        <h2 class="title title--xl">
            <?= View::capture('partials/title', [
                'lines' => (array) ($section['title'] ?? []),
                'outlineFrom' => $section['outlineFrom'] ?? null,
            ]) ?>
        </h2>
        <?php if (!empty($section['body'])): ?>
            <p class="section__body section__body--lead" data-reveal><?= View::e($section['body']) ?></p>
        <?php endif; ?>

        <div class="contact__actions" data-reveal>
            <a class="button" href="<?= View::e($site['contact']['site'] ?? '#') ?>" rel="noopener">
                <?= View::e($site['contact']['rdv'] ?? 'On prend RdV ?') ?>
            </a>
            <a class="link-arrow" href="<?= View::e($site['contact']['linkedin'] ?? '#') ?>" rel="noopener">
                LinkedIn
            </a>
        </div>
    </div>
</section>
