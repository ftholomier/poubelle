<?php /** @var array $pro @var array $inv @var bool $used */ use App\Services\Leads; use App\Services\Pros; ?>
<section class="article">
  <span class="tag">Avis client vérifié</span>
  <h1>Votre avis sur <?= e(Pros::displayName($pro)) ?></h1>
  <?php if ($used): ?>
    <p class="lead">Vous avez déjà utilisé cette invitation. Merci !</p>
  <?php else: ?>
  <form class="form form-card mt-3" method="post">
    <fieldset class="field"><legend class="label">Votre note</legend>
      <div class="star-input"><?php for ($s = 5; $s >= 1; $s--): ?><input type="radio" id="s<?= $s ?>" name="rating" value="<?= $s ?>"<?= $s === 5 ? ' checked' : '' ?>><label for="s<?= $s ?>">★</label><?php endfor; ?></div>
    </fieldset>
    <div class="field"><label for="n">Prénom affiché</label><input id="n" type="text" name="name" value="<?= e($inv['n'] ?? '') ?>" required maxlength="60"></div>
    <div class="field"><label for="t">Événement</label><select id="t" name="event_type"><option value="">—</option><?php foreach (Leads::EVENT_TYPES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="ti">Titre</label><input id="ti" type="text" name="title" maxlength="90"></div>
    <div class="field"><label for="b">Votre avis</label><textarea id="b" name="body" required minlength="15" maxlength="3000"></textarea></div>
    <button class="btn btn-coral" type="submit">Publier mon avis</button>
  </form>
  <?php endif; ?>
</section>
