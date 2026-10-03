<?php /** Message simple. Variables : $title, $text, $back */ ?>
<div class="card card--pad" style="max-width:640px">
  <h2 class="card__t"><?= e($title) ?></h2>
  <p style="margin:0"><?= e($text) ?></p>
  <?php if (!empty($back)): ?><a class="btn btn--navy" href="<?= e($back) ?>" style="align-self:flex-start">← Retour</a><?php endif; ?>
</div>
