<?php
/** @var array $post */
$site = Content::site();
$imgs = $post['images'] ?? [];
$first = $imgs[0] ?? '';
$lead = trim((string) ($post['lead'] ?? ''));
$related = array_slice(array_values(array_filter(Content::posts(), fn($x) => $x['id'] !== $post['id'])), 0, 3);
$url = Http::abs($post['path']);
?>
<div class="art">
  <a class="back" href="<?= e(Content::path('actu')) ?>">← Toutes les actus</a>
  <div class="art__meta">
    <span class="pill"><?= e($post['cat'] ?? '') ?></span>
    <span class="art__d"><?= e(date_fr((string) $post['date'])) ?></span>
  </div>
  <h1><?= e(plain((string) $post['title'])) ?></h1>
  <?php if ($lead !== ''): ?><p class="art__lead"><?= e($lead) ?></p><?php endif; ?>
</div>
<?php if ($first !== ''): ?>
<div class="art__hero" data-lightbox>
  <div><a href="<?= e($first) ?>"><img src="<?= e($first) ?>" alt="<?= e(plain((string) $post['title'])) ?>"></a></div>
</div>
<?php endif; ?>
<div class="art__body">
  <?php if (trim(plain((string) ($post['content'] ?? ''))) !== ''): ?>
    <div class="prose"><?= View::html($post['content']) ?></div>
  <?php endif; ?>
  <?php if (count($imgs) > 1): ?>
    <div class="art__more" data-lightbox>
      <?php foreach (array_slice($imgs, 1) as $im): ?>
        <a class="gtile" href="<?= e($im) ?>"><img src="<?= e($im) ?>" alt="" loading="lazy"><div class="gtile__shade"></div></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($post['link'])): ?>
    <p style="margin-top:22px"><a class="lnk" href="<?= e($post['link']) ?>" target="_blank" rel="noopener">Voir sur Facebook →</a></p>
  <?php endif; ?>
  <div class="share">
    <span class="share__l">Partager</span>
    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($url) ?>" target="_blank" rel="noopener"><span class="fb-f">f</span>Facebook</a>
    <button type="button" data-copy="<?= e($url) ?>">Copier le lien</button>
  </div>
  <div class="minicta">
    <div class="minicta__t"><?= e(Content::page('home')['cta']['title'] ?? '') ?></div>
    <?= View::btn(Content::ui()['cta_bar']['devis'] ?? 'Demandez un devis', Content::path('devis'), 'btn-y btn-yw') ?>
  </div>
</div>
<?php if ($related): ?>
<div class="related">
  <div class="related__t">À lire aussi</div>
  <div class="grid-3">
    <?php foreach ($related as $i => $p): ?><?= View::newsCard($p, $i) ?><?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
