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
  <?php if (!empty(Content::site()['reviews_url'])): ?>
    <div class="more"><a class="lnk" href="<?= e(Content::site()['reviews_url']) ?>" target="_blank" rel="noopener">Voir tous les avis sur Google →</a></div>
  <?php endif; ?>
</div>
<?php View::render('partials/cta_block') ?>
