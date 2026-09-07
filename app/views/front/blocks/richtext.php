<?php /** @var array $block @var array $data */ ?>
<section class="section section--<?= e($block['theme']) ?>"
    <?= !empty($block['anchor']) ? 'id="' . e($block['anchor']) . '"' : '' ?>>
    <div class="shell<?= !empty($data['narrow']) ? ' shell--narrow' : '' ?>">
        <?php if (trRaw($data['title']) !== ''): ?>
            <h2 class="section__title" <?= reveal('up') ?>><?= tr($data['title']) ?></h2>
        <?php endif; ?>
        <div class="prose" <?= reveal('up', 80) ?>><?= safeHtml($data['html']) ?></div>
    </div>
</section>
