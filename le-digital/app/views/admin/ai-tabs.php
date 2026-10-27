<?php
/** @var string $on  onglet actif : settings|costs|writer */
$cur = static fn(string $tab) => $on === $tab ? ' class="is-on" aria-current="page"' : '';
$month = LD\AiCosts::monthUsd() * LD\AiCosts::rate();
?>
<nav class="tabs tabs--section" aria-label="Intelligence artificielle">
  <a href="/admin/ia"<?= $cur('settings') ?>>Réglages & clés API</a>
  <a href="/admin/ia/couts"<?= $cur('costs') ?>>Coûts <small><?= e(number_format($month, $month < 10 ? 2 : 0, ',', ' ')) ?> € ce mois</small></a>
</nav>
