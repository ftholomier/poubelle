<?php
use App\Core\Str;
use App\Core\Url;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Pros;

/** @var array $p entrée d'index publique (+ distance) */
$cat = Categories::get($p['cats'][0] ?? null);
$color = $cat['color'] ?? '#ffd23f';
$img = Pros::coverOf($p, 'sm');
$city = $p['city'] ?: (Geo::commune((string) $p['insee'])['n'] ?? '');
$dist = isset($p['distance']) && $p['distance'] !== null ? (int) round((float) $p['distance']) : null;
?>
<article class="result-card" style="--c:<?= e($color) ?>" data-id="<?= (int) $p['id'] ?>" tabindex="-1">
  <div class="thumb"><?php if ($img): ?><img src="<?= e($img) ?>" alt="" width="112" height="112" loading="lazy" decoding="async"><?php else: ?><?= e(Str::initials($p['name'])) ?><?php endif; ?></div>
  <div class="info">
    <div class="cat"><span><i></i><?= e($cat['name'] ?? 'Animation') ?></span><?php if (!empty($p['hot'])): ?><span class="hot-mini">🔥</span><?php endif; ?></div>
    <h3><a href="<?= e(Url::pro($p)) ?>?src=recherche" data-focus="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a></h3>
    <div class="where">📍 <?= e($city ?: (Geo::dep((string) $p['dep'])['name'] ?? '')) ?><?= $dist !== null ? ' · ' . $dist . ' km' : '' ?></div>
    <?php if ($p['tagline']): ?><div class="line"><?= e($p['tagline']) ?></div><?php endif; ?>
    <div class="bottom">
      <?php if ((int) $p['reviews'] > 0): ?><span class="rating">★ <?= e(number_format((float) $p['rating'], 1, ',', '')) ?></span><span class="muted"><?= (int) $p['reviews'] ?> avis</span><?php endif; ?>
      <span class="muted"><?= !empty($p['price']) ? 'dès ' . nf((int) $p['price']) . ' €' : 'Sur devis' ?></span>
    </div>
  </div>
  <button type="button" class="fav" data-fav="<?= (int) $p['id'] ?>" aria-label="Favori" aria-pressed="false">♥</button>
</article>
