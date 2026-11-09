<?php
/** @var array $p produit */
$ui = Content::ui();
$gallery = Content::gallery();
$others = array_values(array_filter(Content::products(), fn($x) => $x['id'] !== $p['id']));
$devisUrl = Content::path('devis') . '?produit=' . rawurlencode((string) ($p['devis_choice'] ?? $p['title']));
View::render('partials/band', ['eyebrow' => $p['title'], 'h1' => $p['h1'] ?: $p['title'], 'lead' => $p['summary'] ?? '']);
?>
<div class="wrap">
  <div class="pdetail">
    <div class="prose" data-reveal="up"><?= View::html($p['content']) ?></div>
    <aside class="dv__side pdetail__side" data-reveal="right" data-reveal-delay="120">
      <div class="prow__img" style="min-height:240px;cursor:zoom-in" data-lightbox><a href="<?= e($p['image']) ?>" style="position:absolute;inset:0"><img src="<?= e($p['image']) ?>" alt="<?= e($p['title']) ?>"></a></div>
      <div class="dv__inc">
        <div class="dv__inct"><?= e(Content::page('devis')['includes_title'] ?? '') ?></div>
        <div class="dv__incl">
          <?php foreach ((Content::page('devis')['includes'] ?? []) as $d): ?><div><b>✓</b><span><?= e($d) ?></span></div><?php endforeach; ?>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:22px">
          <?= View::btn('Devis pour ce produit', $devisUrl, 'btn-y btn-sm btn-yw') ?>
          <?php if (!empty($ui['bot']['enabled'])): ?>
            <button class="btn btn-ow btn-sm" type="button" data-ask="<?= e($p['title']) ?>" style="font-size:14px"><span class="fx"></span><span>Poser une question</span></button>
          <?php endif; ?>
        </div>
      </div>
      <div class="dv__talk">
        <div class="dv__talkt"><?= e(Content::page('devis')['talk_title'] ?? '') ?></div>
        <a class="dv__tel" href="tel:<?= e(tel(Content::site()['phone'])) ?>"><?= e(Content::site()['phone']) ?></a>
        <div class="dv__talks"><?= e(Content::site()['hours']) ?> · Service technique <?= e(Content::site()['sav_phone']) ?></div>
      </div>
    </aside>
  </div>
</div>

<?php View::render('partials/gallery', ['gallery' => $gallery, 'current' => (string) ($p['gallery_cat'] ?? $p['id']), 'title' => $p['title']]) ?>

<?php if ($others): ?>
<section class="sec">
  <div class="sec__head" data-reveal="up">
    <div><?= View::eyebrow('', Content::page('produits')['title'] ?? 'Nos produits') ?><h2 class="h2">Nos autres produits</h2></div>
    <a class="lnk" href="<?= e(Content::path('produits')) ?>">Voir nos produits →</a>
  </div>
  <div class="grid-prod">
    <?php foreach (array_slice($others, 0, 3) as $i => $o): ?>
      <a class="pcard" href="<?= e($o['path']) ?>" data-reveal="up" data-reveal-delay="<?= $i * 80 ?>">
        <img src="<?= e($o['image']) ?>" alt="<?= e($o['title']) ?>" loading="lazy">
        <div class="pcard__shade"></div>
        <div class="pcard__b"><div class="pcard__bar"></div><div class="pcard__t"><?= e($o['title']) ?></div><div class="pcard__s"><?= e(excerpt((string) $o['summary'], 92)) ?></div></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php View::render('partials/cta_block') ?>
