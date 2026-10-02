<?php /** @var array $pro @var array $inv @var bool $used @var ?string $error @var array $old */ use App\Services\Pros; $old = $old ?? []; ?>
<section class="article">
  <span class="tag">Avis client vérifié</span>
  <h1>Votre avis sur <?= e(Pros::displayName($pro)) ?></h1>
  <?php if ($used): ?>
    <p class="lead">Vous avez déjà utilisé cette invitation. Merci !</p>
  <?php else: ?>
  <?php if (!empty($error)): ?><div class="alert alert-error mt-2"><?= e($error) ?></div><?php endif; ?>
  <form class="form form-card mt-3 review-form" method="post" data-review-form>
    <fieldset class="field review-stars">
      <legend><strong>Votre note</strong></legend>
      <div class="stars-row"><div class="star-input star-input-lg">
        <?php foreach ([5 => 'Excellent !', 4 => 'Très bien', 3 => 'Correct', 2 => 'Décevant', 1 => 'Très décevant'] as $s => $word): ?><input type="radio" id="s<?= $s ?>" name="rating" value="<?= $s ?>" data-word="<?= e($word) ?>" required<?= (int) ($old['rating'] ?? 0) === $s ? ' checked' : '' ?>><label for="s<?= $s ?>" title="<?= e($word) ?>">★<span class="sr-only"><?= $s ?> sur 5 : <?= e($word) ?></span></label><?php endforeach; ?>
      </div>
      <span class="star-hint" data-star-hint aria-live="polite">Cliquez sur une étoile</span></div>
    </fieldset>
    <div class="review-more">
      <div class="field"><label for="b" class="sr-only">Votre avis</label><textarea id="b" name="body" rows="4" required minlength="15" maxlength="3000" placeholder="Racontez votre expérience : ambiance, ponctualité, écoute, rapport qualité-prix…"><?= e((string) ($old['body'] ?? '')) ?></textarea></div>
      <button class="btn btn-coral" type="submit">Publier mon avis</button>
    </div>
  </form>
  <?php endif; ?>
</section>
