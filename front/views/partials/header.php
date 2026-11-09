<?php
/** @var string $active  clé de la page courante (home, produits, product:<id>…) */
$site = Content::site();
$ui = Content::ui();
$menu = Content::menu();
$products = Content::products();
$devis = Content::path('devis');
$isActive = function (array $m) use ($active): bool {
    return $active === $m['key'] || ($m['key'] === 'produits' && str_starts_with((string) $active, 'product:')) || ($m['key'] === 'actu' && str_starts_with((string) $active, 'post:'));
};
?>
<div class="rv-top">
  <div class="rv-top__in">
    <div class="rv-top__l">
      <a class="rv-top__tel" href="tel:<?= e(tel($site['phone'])) ?>"><?= e($site['phone']) ?></a>
      <?php foreach (($ui['topbar']['items'] ?? []) as $i => $t): ?>
        <span class="<?= $i === 0 ? 'd-only' : '' ?>"><?= e($t) ?></span>
      <?php endforeach; ?>
    </div>
    <div class="rv-top__r d-only">
      <?php foreach (($ui['topbar']['right'] ?? []) as $i => $t): ?>
        <?php if ($i > 0): ?><span class="rv-top__sep"></span><?php endif; ?><span><?= e($t) ?></span>
      <?php endforeach; ?>
    </div>
    <?php if (!empty($ui['topbar']['mobile'])): ?><span class="rv-top__m m-only"><?= e($ui['topbar']['mobile']) ?></span><?php endif; ?>
  </div>
</div>

<header class="rv-hdr" id="rv-hdr">
  <div class="rv-hdr__in">
    <a class="rv-logo" href="/" aria-label="<?= e($site['name']) ?> — accueil"><img src="/assets/img/logo.png" alt="<?= e($site['name']) ?>" width="301" height="74"></a>
    <nav class="rv-nav d-only" aria-label="Menu principal">
      <?php foreach ($menu as $m): $href = Content::path($m['key']); ?>
        <?php if (($m['children'] ?? '') === 'products'): ?>
          <div class="rv-nav__dd">
            <a class="rv-nav__a<?= $isActive($m) ? ' is-active' : '' ?>" href="<?= e($href) ?>" aria-haspopup="true"><span><?= e($m['label']) ?></span><i></i></a>
            <div class="rv-dd"><div class="rv-dd__box">
              <?php foreach ($products as $p): ?>
                <a href="<?= e($p['path']) ?>" class="<?= $active === 'product:' . $p['id'] ? 'is-active' : '' ?>"><img src="<?= e($p['image']) ?>" alt="" loading="lazy"><span><?= e($p['title']) ?></span></a>
              <?php endforeach; ?>
            </div></div>
          </div>
        <?php else: ?>
          <a class="rv-nav__a<?= $isActive($m) ? ' is-active' : '' ?>" href="<?= e($href) ?>"><span><?= e($m['label']) ?></span><i></i></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <span class="rv-nav__sep"></span>
      <a class="rv-hbtn" href="<?= e($devis) ?>"><span class="fx"></span><span><?= e($ui['header_button'] ?? 'Devis gratuit →') ?></span></a>
    </nav>
    <div class="rv-mbar">
      <a class="rv-mdevis" href="<?= e($devis) ?>"><?= e($ui['header_button_mobile'] ?? 'Devis') ?></a>
      <button class="rv-burger" id="rv-burger" type="button" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="rv-mmenu"><span></span><span></span><span></span></button>
    </div>
  </div>
  <nav class="rv-mmenu" id="rv-mmenu" hidden aria-label="Menu mobile">
    <?php $devisLink = '<a href="' . e($devis) . '"' . ($active === 'devis' ? ' class="is-active"' : '') . '><span>' . e(rtrim((string) ($ui['header_button'] ?? 'Devis gratuit'), ' →')) . '</span><b>→</b></a>'; $devisDone = false; ?>
    <?php foreach ($menu as $m): ?>
      <?php if ($m['key'] === 'contact' && !$devisDone) { echo $devisLink; $devisDone = true; } ?>
      <a href="<?= e(Content::path($m['key'])) ?>" class="<?= $isActive($m) ? 'is-active' : '' ?>"><span><?= e($m['label']) ?></span><b>→</b></a>
      <?php if (($m['children'] ?? '') === 'products'): foreach ($products as $p): ?>
        <a class="sub" href="<?= e($p['path']) ?>"><span><?= e($p['title']) ?></span><b>→</b></a>
      <?php endforeach; endif; ?>
    <?php endforeach; ?>
    <?php if (!$devisDone) { echo $devisLink; } ?>
    <a class="rv-mtel" href="tel:<?= e(tel($site['phone'])) ?>"><?= e($site['phone']) ?></a>
  </nav>
  <div class="rv-prog"><div id="rv-prog"></div></div>
</header>
