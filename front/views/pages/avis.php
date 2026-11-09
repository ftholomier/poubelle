<?php
$pg = Content::page('avis');
$reviews = Content::reviews();
$rating = Content::rating();
$side = '<div class="fbcard" style="cursor:default"><span style="font-family:var(--arch);font-weight:900;font-size:40px;letter-spacing:-.04em;line-height:1">' . e($rating['label']) . '</span><span><span class="stars">★★★★★</span><small>' . (int) $rating['count'] . ' ' . e($rating['source']) . '</small></span></div>';
View::render('partials/band', ['eyebrow' => $pg['eyebrow'] ?? '', 'h1' => $pg['title'], 'lead' => $pg['lead'] ?? '', 'side' => $side]);
?>
<div class="wrap" style="padding-top:34px">
  <div class="grid-avis">
    <?php foreach ($reviews as $i => $a): ?><?= View::reviewCard($a, ($i % 3) * 90) ?><?php endforeach; ?>
  </div>
  <?php if ($rating['url'] !== '' || $rating['review_url'] !== ''): ?>
    <div class="more" style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center">
      <?php if ($rating['review_url'] !== ''): ?><?= View::btn('Laisser un avis Google', $rating['review_url'], 'btn-y btn-md', ['target' => '_blank', 'rel' => 'noopener']) ?><?php endif; ?>
      <?php if ($rating['url'] !== ''): ?><a class="btn btn-l" href="<?= e($rating['url']) ?>" target="_blank" rel="noopener" style="padding:16px 22px"><span class="fx"></span><span>Voir tous les avis sur Google →</span></a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php View::render('partials/cta_block') ?>
