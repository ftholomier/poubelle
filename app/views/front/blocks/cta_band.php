<?php /** @var array $block @var array $data */
$variant = in_array($data['variant'] ?? 'dark', ['dark', 'accent', 'light'], true) ? $data['variant'] : 'dark';
?>
<section class="section section--cta cta-band cta-band--<?= e($variant) ?>"
    <?= !empty($block['anchor']) ? 'id="' . e($block['anchor']) . '"' : '' ?>>
    <span class="cta-band__glow" aria-hidden="true"></span>
    <div class="shell cta-band__inner">
        <div class="cta-band__text" <?= reveal('right') ?>>
            <h2 class="cta-band__title"><?= tr($data['title']) ?></h2>
            <?php if (trRaw($data['text']) !== ''): ?>
                <p class="cta-band__subtitle"><?= tr($data['text']) ?></p>
            <?php endif; ?>
        </div>
        <div class="cta-band__actions" <?= reveal('left', 120) ?>>
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
    </div>
</section>
