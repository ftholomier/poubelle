<?php /** @var array $block @var array $data */ ?>
<section class="section section--<?= e($block['theme']) ?> section--tight"
    <?= !empty($block['anchor']) ? 'id="' . e($block['anchor']) . '"' : '' ?>>
    <div class="shell">
        <?php if (trRaw($data['title']) !== ''): ?>
            <h2 class="section__title text-center" <?= reveal('up') ?>><?= tr($data['title']) ?></h2>
        <?php endif; ?>
        <ul class="stats">
            <?php foreach ($data['items'] as $i => $item): ?>
                <li class="stats__item" <?= reveal('up', $i * 90) ?>>
                    <span class="stats__value"><?php
                        if (($item['prefix'] ?? '') !== ''): ?><span class="stats__unit"><?= e((string) $item['prefix']) ?></span><?php endif;
                        ?><span data-counter="<?= e((string) ($item['value'] ?? 0)) ?>">0</span><?php
                        if (($item['suffix'] ?? '') !== ''): ?><span class="stats__unit"><?= e((string) $item['suffix']) ?></span><?php endif;
                    ?></span>
                    <span class="stats__label"><?= tr($item['label'] ?? '') ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
