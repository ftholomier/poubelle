<?php
$pg = Content::page('actu');
$site = Content::site();
$posts = Content::posts();
$counts = [];
foreach ($posts as $p) {
    $counts[$p['cat'] ?? ''] = ($counts[$p['cat'] ?? ''] ?? 0) + 1;
}
$side = !empty($site['facebook'])
    ? '<a class="fbcard" href="' . e($site['facebook']) . '" target="_blank" rel="noopener"><span class="fb-f">f</span><span>' . e($pg['facebook_text'] ?? 'Suivez-nous sur Facebook') . '<small>' . e($pg['facebook_title'] ?? 'Voir sur Facebook') . '</small></span></a>'
    : '';
View::render('partials/band', ['eyebrow' => $pg['eyebrow'] ?? '', 'h1' => $pg['title'], 'lead' => $pg['lead'] ?? '', 'side' => $side]);
?>
<div class="wrap" style="padding-top:30px" data-news>
  <div class="chips">
    <button class="chip is-on" type="button" data-filter="">Toutes<small><?= count($posts) ?></small></button>
    <?php foreach (Content::categories() as $c): if (empty($counts[$c])) { continue; } ?>
      <button class="chip" type="button" data-filter="<?= e($c) ?>"><?= e($c) ?><small><?= (int) $counts[$c] ?></small></button>
    <?php endforeach; ?>
  </div>
  <div class="grid-3" style="margin-top:26px">
    <?php foreach ($posts as $i => $p): ?><?= View::newsCard($p, $i) ?><?php endforeach; ?>
  </div>
  <?php if (!$posts): ?><p style="color:#6b6b6b;margin-top:26px">Aucune actualité pour le moment.</p><?php endif; ?>
</div>
