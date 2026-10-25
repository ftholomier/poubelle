<?php
/** @var array $c */
use LD\View;

$reviews = LD\Reviews::all(true);
$tags = LD\Reviews::TAGS;
$placeholders = ['Développement', 'Formation', 'Accompagnement', 'Développement', 'Formation', 'Accompagnement'];
?>
<section class="sec sec--rose">
  <div class="wrap wrap--list">
    <h1 class="list-title list-title--avis"><?= t($c['title']) ?></h1>
    <div class="filters" role="toolbar" aria-label="Filtrer les avis" data-filter="rv">
      <button type="button" class="is-on" aria-pressed="true" data-f="*"><?= t($c['filter_all']) ?></button>
      <?php foreach ($tags as $tg): ?><button type="button" aria-pressed="false" data-f="<?= e($tg) ?>"><?= e($tg) ?></button><?php endforeach; ?>
    </div>
    <div class="af" style="--min:340px" data-filter-list="rv">
      <?php if ($reviews): foreach ($reviews as $r): ?>
        <div class="fitem" data-tag="<?= e($r['tag'] ?? '') ?>"><?= View::partial('review', ['r' => $r, 'variant' => 'page']) ?></div>
      <?php endforeach; else: foreach ($placeholders as $tg): ?>
        <div class="fitem" data-tag="<?= e($tg) ?>"><?= View::partial('review', ['r' => null, 'tag' => $tg, 'who' => '', 'variant' => 'page', 'empty' => $c['empty']]) ?></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>
<?= View::partial('final-dark', ['title' => $c['final_title'], 'text' => null, 'cta' => $c['final_cta'], 'variant' => 'avis']) ?>
