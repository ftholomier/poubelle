<?php
/** @var array $block @var array $data @var array $reviews @var array $settings */
$limit = max(1, (int) ($data['limit'] ?? 6));
$items = array_slice($reviews['items'] ?? [], 0, $limit);
if ($items === []) {
    return;
}
$rating = (float) ($reviews['rating'] ?? 0);
$total  = (int) ($reviews['total'] ?? count($items));
$profil = (string) ($reviews['url'] ?? '');
?>
<section class="section section--<?= e($block['theme']) ?> section--reviews"
    <?= !empty($block['anchor']) ? 'id="' . e($block['anchor']) . '"' : '' ?>>
    <div class="shell">
        <header class="section__head">
            <?php if (trRaw($data['eyebrow']) !== ''): ?>
                <p class="eyebrow" <?= reveal('up') ?>>
                    <?= icon('google', 'eyebrow__brand', 16) ?><?= tr($data['eyebrow']) ?>
                </p>
            <?php endif; ?>
            <?php if (trRaw($data['title']) !== ''): ?>
                <h2 class="section__title" <?= reveal('up', 80) ?>><?= tr($data['title']) ?></h2>
            <?php endif; ?>
            <?php if (trRaw($data['text']) !== ''): ?>
                <p class="section__text" <?= reveal('up', 130) ?>><?= tr($data['text']) ?></p>
            <?php endif; ?>

            <?php if ($rating > 0): ?>
                <div class="rating" <?= reveal('up', 180) ?>>
                    <span class="rating__score"><?= e(number_format($rating, 1, ',', ' ')) ?></span>
                    <span class="rating__stars" role="img" aria-label="<?= e(number_format($rating, 1, ',', ' ')) ?> sur 5">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                            <?= icon('star', 'rating__star' . ($i < round($rating) ? ' is-on' : ''), 18) ?>
                        <?php endfor; ?>
                    </span>
                    <span class="rating__count"><?= e((string) $total) ?> <?= __e('reviews.google') ?></span>
                </div>
            <?php endif; ?>
        </header>

        <div class="reviews">
            <?php foreach ($items as $i => $review): ?>
                <article class="review" <?= reveal('up', $i * 80) ?>>
                    <span class="review__quote" aria-hidden="true"><?= icon('quote', '', 24) ?></span>
                    <div class="review__stars" role="img" aria-label="<?= (int) $review['rating'] ?> étoiles sur 5">
                        <?php for ($s = 0; $s < 5; $s++): ?>
                            <?= icon('star', 'review__star' . ($s < (int) $review['rating'] ? ' is-on' : ''), 14) ?>
                        <?php endfor; ?>
                    </div>
                    <p class="review__text"><?= e((string) $review['text']) ?></p>
                    <footer class="review__footer">
                        <span class="review__avatar" aria-hidden="true">
                            <?php if (!empty($review['avatar'])): ?>
                                <img src="<?= e((string) $review['avatar']) ?>" alt="" width="40" height="40" loading="lazy">
                            <?php else: ?>
                                <?= e(mb_strtoupper(mb_substr((string) $review['author'], 0, 1))) ?>
                            <?php endif; ?>
                        </span>
                        <span class="review__identity">
                            <strong class="review__author"><?= e((string) $review['author']) ?></strong>
                            <?php if (!empty($review['role']) || !empty($review['date'])): ?>
                                <span class="review__meta">
                                    <?= e(trim((string) $review['role'] . (($review['role'] && $review['date']) ? ' · ' : '') . (string) $review['date'])) ?>
                                </span>
                            <?php endif; ?>
                        </span>
                        <?= icon('google', 'review__source', 16) ?>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($profil !== ''): ?>
            <p class="section__foot" <?= reveal('up') ?>>
                <a class="link-underline" href="<?= e($profil) ?>" target="_blank" rel="noopener noreferrer">
                    <span class="link-underline__text"><?= __e('reviews.see_all') ?></span>
                </a>
            </p>
        <?php endif; ?>
    </div>
</section>
