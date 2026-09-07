<?php /** @var array $block @var array $data */
$columns = max(2, min(3, (int) ($data['columns'] ?? 3)));
?>
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

        <div class="cards cards--<?= $columns ?>">
            <?php foreach ($data['items'] as $i => $item):
                $url = (string) ($item['url'] ?? '');
                $tag = $url !== '' ? 'a' : 'div';
                ?>
                <<?= $tag ?> class="card card--service"<?= $url !== '' ? ' href="' . e(u($url)) . '"' : '' ?> <?= reveal('up', $i * 90) ?>>
                    <span class="card__icon" aria-hidden="true"><?= icon((string) ($item['icon'] ?? 'spark'), '', 26) ?></span>
                    <h3 class="card__title"><?= tr($item['title'] ?? '') ?></h3>
                    <p class="card__text"><?= tr($item['text'] ?? '') ?></p>
                    <?php if ($url !== '' && trRaw($item['link_label'] ?? '') !== ''): ?>
                        <span class="card__link">
                            <span class="card__link-text"><?= tr($item['link_label']) ?></span>
                            <?= icon('arrow-right', 'card__link-icon', 16) ?>
                        </span>
                    <?php endif; ?>
                </<?= $tag ?>>
            <?php endforeach; ?>
        </div>
    </div>
</section>
