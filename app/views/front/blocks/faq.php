<?php /** @var array $block @var array $data */
$items = $data['items'] ?? [];
if ($items === []) { return; }
?>
<section class="section section--<?= e($block['theme']) ?>"
    <?= !empty($block['anchor']) ? 'id="' . e($block['anchor']) . '"' : '' ?>>
    <div class="shell shell--narrow">
        <header class="section__head">
            <?php if (trRaw($data['eyebrow']) !== ''): ?>
                <p class="eyebrow" <?= reveal('up') ?>><span class="eyebrow__dot" aria-hidden="true"></span><?= tr($data['eyebrow']) ?></p>
            <?php endif; ?>
            <?php if (trRaw($data['title']) !== ''): ?>
                <h2 class="section__title" <?= reveal('up', 80) ?>><?= tr($data['title']) ?></h2>
            <?php endif; ?>
        </header>

        <div class="faq" data-accordion>
            <?php foreach ($items as $i => $item): ?>
                <div class="faq__item" <?= reveal('up', $i * 70) ?>>
                    <h3 class="faq__heading">
                        <button type="button" class="faq__trigger" aria-expanded="false"
                                aria-controls="faq-<?= e($block['id']) ?>-<?= $i ?>">
                            <span class="faq__question"><?= tr($item['question'] ?? '') ?></span>
                            <span class="faq__sign" aria-hidden="true"></span>
                        </button>
                    </h3>
                    <div class="faq__panel" id="faq-<?= e($block['id']) ?>-<?= $i ?>" hidden>
                        <div class="faq__answer prose"><?= safeHtml($item['answer'] ?? '') ?: '<p>' . tr($item['answer'] ?? '') . '</p>' ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'FAQPage',
    'mainEntity' => array_map(static fn (array $item): array => [
        '@type'          => 'Question',
        'name'           => trRaw($item['question'] ?? ''),
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags(trRaw($item['answer'] ?? ''))],
    ], $items),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>
</script>
