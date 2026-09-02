<?php use App\View; ?>
<section class="section section--stats" id="<?= View::e($section['id']) ?>" data-section>
    <div class="shell">
        <p class="eyebrow" data-reveal><?= View::e($section['eyebrow'] ?? '') ?></p>
        <dl class="stats">
            <?php foreach (($section['items'] ?? []) as $item): ?>
                <div class="stats__item" data-reveal>
                    <dt class="stats__value">
                        <span
                            data-counter="<?= View::e((string) ($item['value'] ?? 0)) ?>"
                            data-suffix="<?= View::e($item['suffix'] ?? '') ?>">0<?= View::e($item['suffix'] ?? '') ?></span>
                    </dt>
                    <dd class="stats__label"><?= View::e($item['label'] ?? '') ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </div>
</section>
