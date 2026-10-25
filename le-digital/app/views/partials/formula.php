<?php
/** Jetons de la formule (A+V+D) x PS x H2H = M, avec leur secret au survol (ou au toucher). */
$tokens = LD\Content::get('global')['formula']['tokens'] ?? [];
?>
<div class="formula" data-reveal="scale" data-formula>
  <?php foreach ($tokens as $tk): $op = trim((string) $tk['tip']) === ''; ?>
    <?php if ($op): ?><span class="formula__op" aria-hidden="true"><?= e($tk['t']) ?></span><?php else: ?><button type="button" class="formula__t" title="<?= e($tk['tip']) ?>" data-tip="<?= e($tk['tip']) ?>"><?= e($tk['t']) ?></button><?php endif; ?>
  <?php endforeach; ?>
</div>
