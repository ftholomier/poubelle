<?php
/** @var array $c */
use LD\View;

$reviews = LD\Reviews::all(true);
$tags = LD\Reviews::TAGS;
$placeholders = ['Développement', 'Formation', 'Accompagnement', 'Développement', 'Formation', 'Accompagnement'];
$badge = LD\GoogleReviews::badge();
$widget = LD\Reviews::widgetPosition('avis');
// Avec un widget d'avis, les emplacements « à fournir » n'ont plus lieu d'être.
$showGrid = $widget !== 'replace' && ($reviews || $widget === '');
$usedTags = array_values(array_intersect($tags, array_column($reviews, 'tag')));
?>
<section class="sec sec--rose">
  <div class="wrap wrap--list">
    <h1 class="list-title list-title--avis"><?= t($c['title']) ?></h1>
    <?php if ($badge): ?><?= View::partial('google-badge', ['badge' => $badge, 'write' => true]) ?><?php endif; ?>
    <?php if ($widget === 'before' || $widget === 'replace'): ?><?= View::partial('reviews-widget') ?><?php endif; ?>
    <?php if ($showGrid): ?>
      <?php if (!$reviews || count($usedTags) > 1): ?>
        <div class="filters" role="toolbar" aria-label="Filtrer les avis" data-filter="rv">
          <button type="button" class="is-on" aria-pressed="true" data-f="*"><?= t($c['filter_all']) ?></button>
          <?php foreach ($reviews ? $usedTags : $tags as $tg): ?><button type="button" aria-pressed="false" data-f="<?= e($tg) ?>"><?= e($tg) ?></button><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="af" style="--min:340px" data-filter-list="rv">
        <?php if ($reviews): foreach ($reviews as $r): ?>
          <div class="fitem" data-tag="<?= e($r['tag'] ?? '') ?>"><?= View::partial('review', ['r' => $r, 'variant' => 'page']) ?></div>
        <?php endforeach; else: foreach ($placeholders as $tg): ?>
          <div class="fitem" data-tag="<?= e($tg) ?>"><?= View::partial('review', ['r' => null, 'tag' => $tg, 'who' => '', 'variant' => 'page', 'empty' => $c['empty']]) ?></div>
        <?php endforeach; endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($widget === 'after'): ?><?= View::partial('reviews-widget') ?><?php endif; ?>
  </div>
</section>
<?= View::partial('final-dark', ['title' => $c['final_title'], 'text' => null, 'cta' => $c['final_cta'], 'variant' => 'avis']) ?>
