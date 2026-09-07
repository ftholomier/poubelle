<?php /** @var array $block @var array $data */
$posts = App\Content\Pages::posts((int) ($data['limit'] ?? 3));
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
        </header>

        <?php if ($posts === []): ?>
            <p class="section__text text-center"><?= __e('posts.empty') ?></p>
        <?php else: ?>
            <div class="cards cards--3">
                <?php foreach ($posts as $i => $post): ?>
                    <a class="card card--post" href="<?= e(u('/' . $post['slug'])) ?>" <?= reveal('up', $i * 90) ?>>
                        <span class="card__media">
                            <?php if (!empty($post['cover'])): ?>
                                <img src="<?= e((string) $post['cover']) ?>" alt="" loading="lazy" width="480" height="280">
                            <?php else: ?>
                                <span class="card__media-glyph" aria-hidden="true"><?= icon('document', '', 30) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="card__body">
                            <?php if (!empty($post['published_at'])): ?>
                                <time class="card__date" datetime="<?= e(substr((string) $post['published_at'], 0, 10)) ?>">
                                    <?= e(date('d/m/Y', strtotime((string) $post['published_at']) ?: time())) ?>
                                </time>
                            <?php endif; ?>
                            <h3 class="card__title"><?= tr($post['title']) ?></h3>
                            <p class="card__text"><?= tr($post['excerpt']) ?></p>
                            <span class="card__link">
                                <span class="card__link-text"><?= __e('posts.read') ?></span>
                                <?= icon('arrow-right', 'card__link-icon', 16) ?>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
