<?php
use App\Core\Str;
use App\Core\Url;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Pros;

/** @var array $p entrée d'index publique */
$cat = Categories::get($p['cats'][0] ?? null);
$color = $cat['color'] ?? '#ffd23f';
$url = Url::pro($p);
$img = Pros::coverOf($p, 'sm');
$city = $p['city'] ?: (Geo::commune((string) $p['insee'])['n'] ?? '');
$dist = isset($p['distance']) && $p['distance'] !== null ? (int) round((float) $p['distance']) : null;
$lazy = ($lazy ?? true) ? ' loading="lazy" decoding="async"' : ' fetchpriority="high"';
?>
<article class="pro-card" style="--c:<?= e($color) ?>" data-id="<?= (int) $p['id'] ?>">
  <div class="photo">
    <?php if ($img): ?>
      <img src="<?= e($img) ?>" alt="<?= e($p['name'] . ' — ' . ($cat['name'] ?? 'animation') . ($city ? ' ' . Geo::inCity($city) : '')) ?>" width="480" height="320"<?= $lazy ?>>
    <?php else: ?>
      <span class="initials" aria-hidden="true"><?= e(Str::initials($p['name'])) ?></span>
    <?php endif; ?>
    <span class="tag"><?= e($cat['name'] ?? 'Animation') ?></span>
    <button type="button" class="fav" data-fav="<?= (int) $p['id'] ?>" aria-label="Ajouter <?= e($p['name']) ?> aux favoris" aria-pressed="false">♥</button>
    <?php if (!empty($p['hot'])): ?><span class="hot">🔥 Très demandé</span><?php endif; ?>
  </div>
  <div class="body">
    <h3><a href="<?= e($url) ?>"><?= e($p['name']) ?></a></h3>
    <div class="tagline"><?= e($p['tagline'] ?: ($cat['tagline'] ?? '')) ?></div>
    <div class="meta">
      <?php if ($city): ?><span>📍 <?= e($city) ?><?= $dist !== null && $dist > 0 ? ' · ' . $dist . ' km' : '' ?></span><?php endif; ?>
      <?php if ((int) $p['reviews'] > 0): ?>
        <span class="rating">★ <?= e(number_format((float) $p['rating'], 1, ',', '')) ?></span><span class="muted"><?= (int) $p['reviews'] ?> avis</span>
      <?php else: ?>
        <span class="new">Nouveau sur l'annuaire</span>
      <?php endif; ?>
    </div>
    <div class="foot">
      <div class="price"><?php if (!empty($p['price'])): ?>dès <strong><?= nf((int) $p['price']) ?> €</strong><?php else: ?><span class="ask">Sur devis</span><?php endif; ?></div>
      <a href="<?= e($url) ?>#contact" class="btn-devis">Demander un devis</a>
    </div>
  </div>
</article>
