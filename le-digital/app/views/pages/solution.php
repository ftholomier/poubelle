<?php
/** Détail d'une solution. @var array $c @var array $so @var array $others @var bool $preview */
use LD\View;
?>
<nav class="sol-crumbs<?= $so['kind'] === 'intensiti' || ($so['theme'] ?? '') === 'sombre' ? ' sol-crumbs--dark' : '' ?>" aria-label="Fil d’Ariane">
  <div class="wrap">
    <a href="/mes-solutions">← Mes solutions</a><span aria-hidden="true">/</span><span aria-current="page"><?= t($so['name']) ?></span>
    <?php if ($preview): ?><span class="sol-crumbs__pv">Aperçu : solution masquée sur le site</span><?php endif; ?>
  </div>
</nav>
<?php if ($so['kind'] === 'intensiti'): ?>
  <?= View::partial('solution-intensiti', ['in' => $so, 'c' => $c]) ?>
<?php else: ?>
  <?= View::partial('solution-standard', ['so' => $so, 'flip' => false]) ?>
<?php endif; ?>

<?php if ($others): ?>
  <section class="sec sec--rose">
    <div class="wrap wrap--others">
      <div class="head-split"><h2 class="h2">Découvrir aussi</h2><a class="link-u" href="/mes-solutions">Toutes mes solutions →</a></div>
      <div class="solgrid<?= count($others) === 1 ? ' solgrid--one' : '' ?>">
        <?php foreach ($others as $i => $o): ?><?= View::partial('solution-card', ['so' => $o, 'i' => $i]) ?><?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?= View::partial('final-dark', ['title' => $c['final']['title'], 'text' => $c['final']['text'], 'cta' => $c['final']['cta'], 'variant' => 'sol']) ?>
