<?php use App\View; ?>
<section class="section section--cards" id="<?= View::e($section['id']) ?>" data-section>
    <div class="shell">
        <p class="eyebrow" data-reveal><?= View::e($section['eyebrow'] ?? '') ?></p>
        <h2 class="title title--lg">
            <?= View::capture('partials/title', [
                'lines' => (array) ($section['title'] ?? []),
                'outlineFrom' => $section['outlineFrom'] ?? null,
            ]) ?>
        </h2>

        <ul class="cards">
            <?php foreach (($section['cards'] ?? []) as $card): ?>
                <li class="card" data-reveal>
                    <span class="card__num"><?= View::e($card['num'] ?? '') ?></span>
                    <h3 class="card__title"><?= View::e($card['title'] ?? '') ?></h3>
                    <p class="card__mode"><?= View::e($card['mode'] ?? '') ?></p>
                    <p class="card__goal">↑ <?= View::e($card['goal'] ?? '') ?></p>
                    <p class="card__text"><?= View::e($card['text'] ?? '') ?></p>
                    <span class="card__cta">Ça m'intéresse !</span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
