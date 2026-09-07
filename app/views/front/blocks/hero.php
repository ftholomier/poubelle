<?php
/** @var array $block @var array $data @var array $settings */
$centered = ($data['variant'] ?? 'split') === 'centered';
?>
<section class="section section--hero section--dark<?= $centered ? ' section--hero-centered' : '' ?>"
    <?= !empty($block['anchor']) ? 'id="' . e($block['anchor']) . '"' : '' ?>>
    <span class="hero__aura" aria-hidden="true"></span>
    <span class="hero__grid" aria-hidden="true"></span>

    <div class="shell hero__inner">
        <div class="hero__content">
            <?php if (trRaw($data['eyebrow']) !== ''): ?>
                <p class="eyebrow eyebrow--light" <?= reveal('up') ?>><span class="eyebrow__dot" aria-hidden="true"></span><?= tr($data['eyebrow']) ?></p>
            <?php endif; ?>

            <h1 class="hero__title" <?= reveal('up', 80) ?>>
                <?= tr($data['title']) ?>
                <?php if (trRaw($data['highlight']) !== ''): ?>
                    <span class="hero__highlight"><?= tr($data['highlight']) ?><span class="hero__underline" aria-hidden="true"></span></span>
                <?php endif; ?>
            </h1>

            <?php if (trRaw($data['text']) !== ''): ?>
                <p class="hero__text" <?= reveal('up', 160) ?>><?= tr($data['text']) ?></p>
            <?php endif; ?>

            <div class="hero__actions" <?= reveal('up', 240) ?>>
                <?php if (trRaw($data['primary_label']) !== ''): ?>
                    <a class="btn btn--accent btn--slide btn--halo btn--lg" href="<?= e(u((string) $data['primary_url'])) ?>">
                        <span class="btn__halo" aria-hidden="true"></span>
                        <span class="btn__label"><?= tr($data['primary_label']) ?></span>
                        <?= icon('arrow-right', 'btn__icon btn__icon--end', 18) ?>
                    </a>
                <?php endif; ?>
                <?php if (trRaw($data['secondary_label']) !== ''): ?>
                    <a class="btn btn--outline-light btn--slide btn--lg" href="<?= e(u((string) $data['secondary_url'])) ?>">
                        <span class="btn__label"><?= tr($data['secondary_label']) ?></span>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($data['badges'])): ?>
                <ul class="hero__badges" <?= reveal('up', 320) ?>>
                    <?php foreach ($data['badges'] as $i => $badge): ?>
                        <li class="hero__badge"><?= icon('check', 'hero__badge-icon', 15) ?><?= tr($badge['label'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if (!$centered): ?>
            <div class="hero__visual" <?= reveal('zoom', 200) ?>>
                <?php if (!empty($data['image'])): ?>
                    <figure class="hero__portrait">
                        <img class="hero__image" src="<?= e(asset((string) $data['image'])) ?>"
                             alt="<?= e(trRaw($data['image_alt'])) ?>" width="1200" height="750"
                             loading="eager" fetchpriority="high">
                    </figure>
                <?php else: ?>
                    <div class="hero__card">
                        <span class="hero__card-glyph" aria-hidden="true"><?= icon('glasses', '', 48) ?></span>
                        <p class="hero__card-title"><?= e((string) ($settings['legal']['director'] ?? '')) ?></p>
                        <p class="hero__card-role"><?= tr($settings['site']['tagline'] ?? '') ?></p>
                        <ul class="hero__card-list">
                            <li><?= icon('check', '', 16) ?> Expertise comptable</li>
                            <li><?= icon('check', '', 16) ?> Conseil &amp; accompagnement</li>
                            <li><?= icon('check', '', 16) ?> Stratégie financière</li>
                        </ul>
                    </div>
                    <span class="hero__float hero__float--1" aria-hidden="true"><?= icon('chart', '', 22) ?></span>
                    <span class="hero__float hero__float--2" aria-hidden="true"><?= icon('shield', '', 22) ?></span>
                    <span class="hero__float hero__float--3" aria-hidden="true"><?= icon('growth', '', 22) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
