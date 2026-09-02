<?php use App\View; ?>
<section class="section section--cards" id="<?= View::e($section['id']) ?>" data-section>
    <div class="shell">
        <?php if (!empty($section['eyebrow'])): ?>
            <p class="eyebrow" data-reveal><?= View::e($section['eyebrow']) ?></p>
        <?php endif; ?>
        <h2 class="title title--lg">
            <?= View::capture('partials/title', [
                'lines' => (array) ($section['title'] ?? []),
                'outlineFrom' => $section['outlineFrom'] ?? null,
            ]) ?>
        </h2>

        <ul class="cards">
            <?php foreach (($section['cards'] ?? []) as $card): ?>
                <li class="card" data-reveal>
                    <?php /* Chaque ligne est facultative : une carte peut se limiter
                             à un intitulé, sans laisser de flèche ni de puce vides. */ ?>
                    <?php if (!empty($card['num'])): ?>
                        <span class="card__num"><?= View::e($card['num']) ?></span>
                    <?php endif; ?>
                    <h3 class="card__title"><?= View::e($card['title'] ?? '') ?></h3>
                    <?php if (!empty($card['mode'])): ?>
                        <p class="card__mode"><?= View::e($card['mode']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($card['goal'])): ?>
                        <p class="card__goal">↑ <?= View::e($card['goal']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($card['text'])): ?>
                        <p class="card__text"><?= View::e($card['text']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($card['cta'])): ?>
                        <span class="card__cta"><?= View::e($card['cta']) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
