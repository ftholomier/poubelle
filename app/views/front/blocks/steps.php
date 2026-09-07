<?php /** @var array $block @var array $data */ ?>
<section class="section section--<?= e($block['theme']) ?>"
    <?= !empty($block['anchor']) ? 'id="' . e($block['anchor']) . '"' : '' ?>>
    <div class="shell">
        <header class="section__head">
            <?php if (trRaw($data['eyebrow']) !== ''): ?>
                <p class="eyebrow" <?= reveal('up') ?>><span class="eyebrow__dot" aria-hidden="true"></span><?= tr($data['eyebrow']) ?></p>
            <?php endif; ?>
            <?php if (trRaw($data['title']) !== ''): ?>
                <h2 class="section__title" <?= reveal('up', 80) ?>><?= tr($data['title']) ?></h2>
            <?php endif; ?>
            <?php if (trRaw($data['text']) !== ''): ?>
                <p class="section__text" <?= reveal('up', 140) ?>><?= tr($data['text']) ?></p>
            <?php endif; ?>
        </header>

        <ol class="steps">
            <?php foreach ($data['items'] as $i => $item): ?>
                <li class="steps__item" <?= reveal('up', $i * 110) ?>>
                    <span class="steps__number" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <div class="steps__body">
                        <h3 class="steps__title"><?= tr($item['title'] ?? '') ?></h3>
                        <p class="steps__text"><?= tr($item['text'] ?? '') ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
