<?php $t = (array) content('testimonials'); $items = (array) ($t['items'] ?? []); if (!$items) return; ?>
<section class="section section--tight" aria-labelledby="avis-title">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow"><?= e($t['eyebrow'] ?? '') ?></span>
      <h2 class="h2" id="avis-title"><?= e($t['title'] ?? '') ?></h2>
      <p class="lead"><?= e($t['lead'] ?? '') ?></p>
    </div>
  </div>
  <div class="reviews-wrap" data-reveal>
    <div class="reviews">
      <?php foreach (array_merge($items, $items) as $r): ?>
        <article class="review">
          <span class="stars" aria-label="<?= e((string) ($r['rating'] ?? 5)) ?> étoiles sur 5">
            <?php for ($i = 0; $i < (int) ($r['rating'] ?? 5); $i++) { echo icon('star'); } ?>
          </span>
          <p class="review__text">« <?= e($r['text'] ?? '') ?> »</p>
          <div class="review__foot">
            <span class="review__avatar" aria-hidden="true"><?= e(initials((string) ($r['author'] ?? ''))) ?></span>
            <span>
              <span class="review__name"><?= e($r['author'] ?? '') ?></span><br>
              <span class="review__src">Avis publié sur <?= e($r['source'] ?? 'Google') ?></span>
            </span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
