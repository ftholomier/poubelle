<?php
$pg = Content::page('produits');
$products = Content::products();
$gallery = Content::gallery();
$ui = Content::ui();
View::render('partials/band', ['eyebrow' => $pg['eyebrow'] ?? $pg['title'], 'h1' => $pg['h1'] ?: $pg['title'], 'lead' => plain((string) ($pg['lead_short'] ?? ''))]);
?>
<?php if (!empty($pg['lead'])): ?>
<div class="page-body" style="padding-top:44px">
  <div class="prose" data-reveal="up"><?= View::html($pg['lead']) ?></div>
</div>
<?php endif; ?>

<div class="wrap" style="padding-top:8px">
  <?php if (!empty($pg['offer_title'])): ?>
    <div class="sec__head" style="padding-top:44px" data-reveal="up">
      <div><?= View::eyebrow('', $pg['title']) ?><h2 class="h2"><?= e($pg['offer_title']) ?></h2></div>
    </div>
  <?php endif; ?>
  <?php foreach ($products as $p): ?>
    <div class="prow" data-reveal="up" id="<?= e($p['id']) ?>">
      <a class="prow__img" href="<?= e($p['path']) ?>"><img src="<?= e($p['image']) ?>" alt="<?= e($p['title']) ?>" loading="lazy"></a>
      <div>
        <div class="prow__tag"><?= e($p['title']) ?></div>
        <h2><a href="<?= e($p['path']) ?>"><?= e($p['offer_title'] ?: $p['title']) ?></a></h2>
        <div class="ybar"></div>
        <p class="prow__p"><?= e($p['summary']) ?></p>
        <?php if (!empty($p['bullets'])): ?>
          <ul class="checks"><?php foreach ($p['bullets'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <div class="prow__btns">
          <?= View::btn('Devis pour ce produit', Content::path('devis') . '?produit=' . rawurlencode((string) ($p['devis_choice'] ?? $p['title'])), 'btn-y btn-sm') ?>
          <a class="btn btn-l" href="<?= e($p['path']) ?>"><span class="fx"></span><span>En savoir plus</span></a>
          <?php if (!empty($ui['bot']['enabled'])): ?>
            <button class="btn btn-l" type="button" data-ask="<?= e($p['title']) ?>"><span class="fx"></span><span>Poser une question</span></button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!empty($pg['why'])): ?>
    <div class="why prose" data-reveal="up"><?= View::html($pg['why']) ?></div>
  <?php endif; ?>
</div>

<?php View::render('partials/gallery', ['gallery' => $gallery, 'current' => '']) ?>
<?php View::render('partials/cta_block') ?>
