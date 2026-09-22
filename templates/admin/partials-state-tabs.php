<?php
/**
 * Onglets d'état. Sans eux, une fiche en attente de modération se perdait au
 * milieu de plusieurs centaines de lignes.
 *
 * @var array  $counts  nombre de fiches par état
 * @var string $filter  état affiché, vide pour tout
 * @var array  $labels  état => libellé
 */
$total = array_sum($counts);
?>
<div class="state-tabs" role="group" aria-label="Filtrer par état">
  <a class="chip<?= $filter === '' ? ' is-on' : '' ?>" href="?"
     <?= $filter === '' ? 'aria-current="true"' : '' ?>>Tout <span class="n"><?= (int) $total ?></span></a>
  <?php foreach ($labels as $key => $label): ?>
    <?php if (($counts[$key] ?? 0) === 0 && $filter !== $key) { continue; } ?>
    <a class="chip<?= $filter === $key ? ' is-on' : '' ?>" href="?etat=<?= e($key) ?>"
       <?= $filter === $key ? 'aria-current="true"' : '' ?>>
      <?= e($label) ?> <span class="n"><?= (int) ($counts[$key] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
</div>
