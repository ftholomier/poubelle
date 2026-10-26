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
  <div class="wrap wrap--solgrid">
    <?php if ($solutions): ?>
      <div class="solgrid<?= count($solutions) === 1 ? ' solgrid--one' : '' ?>">
        <?php foreach ($solutions as $i => $so): ?><?= View::partial('solution-card', ['so' => $so, 'i' => $i]) ?><?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="sol-empty">De nouvelles solutions arrivent bientôt.</p>
    <?php endif; ?>
  </div>
</section>
<script>
// Anciens liens « /mes-solutions#intensiti » : ouverture directe de la page de la solution.
(function () { var id = decodeURIComponent(location.hash.slice(1)); if (!id) return; var a = document.querySelector('[data-sol="' + id.replace(/["\\]/g, '') + '"]'); if (a) location.replace(a.href); })();
</script>

<?= View::partial('final-dark', ['title' => $c['final']['title'], 'text' => $c['final']['text'], 'cta' => $c['final']['cta'], 'variant' => 'sol']) ?>
