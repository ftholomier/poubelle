<?php /** @var array $block @var array $data */
$items = $data['items'] ?? [];
if ($items === []) { return; }
?>
<section class="section section--tight section--<?= e($block['theme']) ?>">
    <div class="shell">
        <?php if (trRaw($data['title']) !== ''): ?>
            <p class="logos__title" <?= reveal('up') ?>><?= tr($data['title']) ?></p>
        <?php endif; ?>
        <ul class="logos">
            <?php foreach ($items as $i => $item): ?>
                <li class="logos__item" <?= reveal('up', $i * 60) ?>>
                    <img src="<?= e(asset((string) ($item['image'] ?? ''))) ?>"
                         alt="<?= e((string) ($item['alt'] ?? '')) ?>"
                         loading="lazy" width="170" height="159">
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
