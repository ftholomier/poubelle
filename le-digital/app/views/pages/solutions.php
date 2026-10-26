<?php
/** @var array $c */
use LD\View;

$solutions = LD\Solutions::enabled();
?>
<section class="sec sec--rose">
  <div class="wrap wrap--sol-hero">
    <h1 class="sol-title"><?= t($c['hero']['title']) ?> <span class="grad-text"><?= t($c['hero']['title_accent']) ?></span></h1>
    <p class="sol-p"><?= t($c['hero']['text']) ?></p>
  </div>
</section>

<?php $std = 0; foreach ($solutions as $so): ?>
  <?php if ($so['kind'] === 'intensiti'): ?>
    <?= View::partial('solution-intensiti', ['in' => $so, 'c' => $c]) ?>
  <?php else: ?>
    <?= View::partial('solution-standard', ['so' => $so, 'flip' => $std++ % 2 === 1]) ?>
  <?php endif; ?>
<?php endforeach; ?>

<?= View::partial('final-dark', ['title' => $c['final']['title'], 'text' => $c['final']['text'], 'cta' => $c['final']['cta'], 'variant' => 'sol']) ?>
