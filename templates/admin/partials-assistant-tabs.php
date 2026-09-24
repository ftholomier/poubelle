<?php
/**
 * Onglets de la rubrique Assistant IA : ce qui a été demandé, et ce qu'on lui a appris.
 *
 * @var string $current  chemin de l'écran affiché
 */
$tabs = ['/admin/assistant' => 'Échanges', '/admin/documents' => 'Documents'];
?>
<div class="state-tabs" role="navigation" aria-label="Assistant IA">
  <?php foreach ($tabs as $href => $label): ?>
    <a class="chip<?= $current === $href ? ' is-on' : '' ?>" href="<?= e($href) ?>"
       <?= $current === $href ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</div>
