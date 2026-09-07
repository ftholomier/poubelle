<?php /** @var array $block @var array $data */
$side = ($data['side'] ?? 'right') === 'left' ? 'left' : 'right';
?>
<section class="section section--<?= e($block['theme']) ?>"
    <?= !empty($block['anchor']) ? 'id="' . e($block['anchor']) . '"' : '' ?>>
    <div class="shell split split--<?= $side ?>">
        <div class="split__media" <?= reveal($side === 'right' ? 'left' : 'right') ?>>
            <?php if (!empty($data['image'])): ?>
                <img class="split__image" src="<?= e((string) $data['image']) ?>"
                     alt="<?= e(trRaw($data['image_alt'])) ?>" loading="lazy" width="620" height="700">
            <?php else: ?>
                <div class="split__placeholder" aria-hidden="true">
                    <?= icon('glasses', '', 64) ?>
                    <span class="split__placeholder-ring"></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="split__content" <?= reveal($side === 'right' ? 'right' : 'left', 120) ?>>
            <?php if (trRaw($data['eyebrow']) !== ''): ?>
                <p class="eyebrow"><span class="eyebrow__dot" aria-hidden="true"></span><?= tr($data['eyebrow']) ?></p>
            <?php endif; ?>
            <?php if (trRaw($data['title']) !== ''): ?>
                <h2 class="section__title"><?= tr($data['title']) ?></h2>
            <?php endif; ?>

            <div class="prose"><?= safeHtml($data['html']) ?></div>

            <?php if (!empty($data['bullets'])): ?>
                <ul class="ticks">
                    <?php foreach ($data['bullets'] as $i => $bullet): ?>
                        <li class="ticks__item" <?= reveal('up', 60 + $i * 70) ?>>
                            <?= icon('check', 'ticks__icon', 16) ?><?= tr($bullet['text'] ?? '') ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (trRaw($data['cta_label']) !== ''): ?>
                <a class="btn btn--primary btn--slide" href="<?= e(u((string) $data['cta_url'])) ?>">
                    <span class="btn__label"><?= tr($data['cta_label']) ?></span>
                    <?= icon('arrow-right', 'btn__icon btn__icon--end', 16) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>
