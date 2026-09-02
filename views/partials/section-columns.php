<?php

use App\View;

$marquee = (string) ($section['marquee'] ?? '');
$repeat = max(2, min((int) ($section['repeat'] ?? 3), 8));
?>
<section class="section section--columns" id="<?= View::e($section['id']) ?>" data-section>
    <?php if ($marquee !== ''): ?>
        <div
            class="marquee marquee--tight"
            data-marquee
            data-speed="<?= View::e((string) ($section['speed'] ?? 0.7)) ?>"
            aria-label="<?= View::e($marquee) ?>">
            <?php for ($track = 0; $track < 2; $track++): ?>
                <div class="marquee__track" <?= $track === 1 ? 'aria-hidden="true"' : '' ?>>
                    <?php for ($i = 0; $i < $repeat; $i++): ?>
                        <span class="marquee__item<?= $i % 2 ? ' marquee__item--outline' : '' ?>">
                            <?= View::e($marquee) ?>
                        </span>
                    <?php endfor; ?>
                </div>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

    <div class="shell">
        <div class="columns">
            <?php foreach (($section['columns'] ?? []) as $column): ?>
                <div class="columns__col" data-reveal>
                    <h3 class="columns__title"><?= View::e($column['title'] ?? '') ?></h3>
                    <ul class="columns__list">
                        <?php foreach (($column['items'] ?? []) as $item): ?>
                            <li class="columns__item"><span><?= View::e($item) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
