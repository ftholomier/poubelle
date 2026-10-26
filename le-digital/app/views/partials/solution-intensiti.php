<?php
/** Bloc riche d'Intensiti (textes : Contenus › Mes solutions). @var array $in @var array $c */
?>
<section class="sec itn" id="<?= e($in['id'] ?? 'intensiti') ?>">
  <div class="itn__glow" aria-hidden="true"></div>
  <div class="wrap sol-grid itn__hero">
    <div class="sol-txt" data-reveal>
      <?php if (!empty($in['logo'])): ?><img class="itn__logo" src="<?= e($in['logo']) ?>" alt="<?= e($in['name']) ?>" width="300" height="61" loading="lazy"><?php else: ?><span class="sol-name"><?= t($in['name']) ?></span><?php endif; ?>
      <?php if (trim((string) ($in['kicker'] ?? '')) !== ''): ?><span class="itn__kicker"><?= fr_flag() ?><?= t($in['kicker']) ?></span><?php endif; ?>
      <h2 class="itn__title"><?= t($in['tagline']) ?><?php if (trim((string) ($in['tagline_accent'] ?? '')) !== ''): ?> <span><?= t($in['tagline_accent']) ?></span><?php endif; ?></h2>
      <p class="sol-p2"><?= t($in['text']) ?></p>
      <?php if (trim((string) ($in['price'] ?? '')) !== ''): ?><p class="itn__price"><b><?= t($in['price']) ?></b><span><?= t($in['price_note'] ?? '') ?></span></p><?php endif; ?>
      <div class="chips"><?php foreach ($in['strengths'] as $st): ?><span class="chip-dk"><?= t($st) ?></span><?php endforeach; ?></div>
      <div class="btns mt6">
        <?php if (!empty($in['trial_url'])): ?><a class="btn itn__btn-lime btn-sol"<?= link_attrs($in['trial_url']) ?>><?= t($in['trial_cta']) ?></a><?php endif; ?>
        <a class="btn btn-ghost-dark btn-sol2"<?= link_attrs($in['url'] ?: '#') ?>><?= t($in['cta']) ?> ↗</a>
      </div>
      <?php if (!empty($in['demo_url'])): ?><a class="itn__demo"<?= link_attrs($in['demo_url']) ?>>▶ <?= t($in['demo_cta']) ?></a><?php endif; ?>
      <?php if (trim((string) $in['url']) === ''): ?><p class="sol-todo"><?= t($c['links_todo']) ?></p><?php endif; ?>
    </div>
    <figure class="screen itn-screen" data-reveal="scale">
      <div class="screen__frame"><div class="screen__in">
        <?php if (!empty($in['video'])): ?>
          <video class="itn-video" muted loop playsinline preload="none" poster="<?= e($in['image']) ?>" aria-label="Démonstration réelle du back-office Intensiti" data-itn-video>
            <source src="<?= e($in['video']) ?>.webm" type="video/webm"><source src="<?= e($in['video']) ?>.mp4" type="video/mp4">
          </video>
        <?php else: ?>
          <?= slot($in['image'], 'Capture Intensiti — à fournir', 'Capture d’écran Intensiti', ['sizes' => '(max-width: 600px) 90vw, 560px']) ?>
        <?php endif; ?>
      </div></div>
      <div class="screen__neck" aria-hidden="true"></div>
      <div class="screen__foot" aria-hidden="true"></div>
      <?php if (!empty($in['video_caption'])): ?><figcaption class="itn-screen__cap"><?= t($in['video_caption']) ?></figcaption><?php endif; ?>
    </figure>
  </div>

  <?php if (!empty($in['steps'])): ?>
    <div class="wrap itn__block">
      <h3 class="itn__h"><?= t($in['steps_title']) ?></h3>
      <ol class="itn-steps">
        <?php foreach ($in['steps'] as $i => $st): ?>
          <li class="itn-step" data-reveal data-delay="<?= $i * 90 ?>"><span class="itn-step__n"><?= sprintf('%02d', $i + 1) ?></span><span class="itn-step__tag"><?= t($st['tag']) ?></span><b><?= t($st['title']) ?></b><p><?= t($st['text']) ?></p></li>
        <?php endforeach; ?>
      </ol>
    </div>
  <?php endif; ?>

  <?php if (!empty($in['places'])): $live = $in['live'] ?? []; ?>
    <div class="wrap itn__block">
      <h3 class="itn__h"><?= t($in['places_title']) ?></h3>
      <div class="itn-places">
        <?php if (!empty($live['brand'])): ?>
          <div class="itn-live" data-itn-live data-slides="<?= json_attr(array_map(static fn($sl) => array_pad(explode('|', (string) $sl, 2), 2, ''), $live['slides'] ?? [])) ?>" role="img" aria-label="Exemple d’écran Intensiti en direct">
            <div class="itn-live__bar"><span class="itn-live__brand"><i><?= e(mb_substr($live['brand'], 0, 1)) ?></i><?= e($live['brand']) ?></span><span class="itn-live__meta"><?= e($live['city'] ?? '') ?> <b data-itn-clock>12:14</b></span></div>
            <div class="itn-live__slide"><span class="itn-live__badge">● EN DIRECT</span><b data-itn-title></b><span data-itn-sub></span></div>
            <div class="itn-live__ticker"><span class="itn-live__info">INFO</span><div class="itn-live__run"><div class="itn-live__track"><span><?= e($live['ticker'] ?? '') ?></span><span aria-hidden="true"><?= e($live['ticker'] ?? '') ?></span></div></div></div>
          </div>
        <?php endif; ?>
        <?php foreach ($in['places'] as $i => $pl): ?>
          <figure class="itn-place<?= $i === 0 ? ' itn-place--big' : '' ?>" data-reveal data-delay="<?= $i * 80 ?>"><?= img_tag($pl['image'], $pl['label'], ['sizes' => $i === 0 ? '(max-width: 760px) 92vw, 640px' : '(max-width: 760px) 92vw, 420px']) ?><figcaption><?= t($pl['label']) ?></figcaption></figure>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($in['features'])): ?>
    <div class="wrap itn__block">
      <div class="itn__head"><h3 class="itn__h"><?= t($in['features_title']) ?></h3><?php if (!empty($in['features_url'])): ?><a class="itn__link"<?= link_attrs($in['features_url']) ?>><?= t($in['features_cta']) ?> ↗</a><?php endif; ?></div>
      <div class="itn-feats">
        <?php foreach ($in['features'] as $i => $ft): ?>
          <div class="itn-feat" data-reveal data-delay="<?= ($i % 4) * 70 ?>"><span><?= sprintf('%02d', $i + 1) ?></span><b><?= t($ft['title']) ?></b><p><?= t($ft['text']) ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($in['hardware']) || !empty($in['pricing']['price'])): $pr = $in['pricing'] ?? []; ?>
    <div class="wrap itn__block itn-buy">
      <div class="itn-hw">
        <div class="itn__head"><div><h3 class="itn__h"><?= t($in['hardware_title']) ?></h3><p class="itn__sub"><?= t($in['hardware_text']) ?></p></div><?php if (!empty($in['hardware_url'])): ?><a class="itn__link"<?= link_attrs($in['hardware_url']) ?>>Le matériel en détail ↗</a><?php endif; ?></div>
        <div class="itn-hw__grid">
          <?php foreach ($in['hardware'] ?? [] as $hw): ?>
            <div class="itn-hw__it" data-reveal><div class="itn-hw__img"><?= img_tag($hw['image'], $hw['title'], ['sizes' => '(max-width: 760px) 45vw, 220px']) ?></div><span class="itn-hw__tag"><?= t($hw['tag']) ?></span><b><?= t($hw['title']) ?></b><p><?= t($hw['text']) ?></p></div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if (!empty($pr['price'])): ?>
        <div class="itn-price" data-reveal="scale">
          <span class="itn-price__k"><?= t($pr['kicker']) ?></span>
          <p class="itn-price__v"><b><?= t($pr['price']) ?></b><span><?= t($pr['unit']) ?></span></p>
          <p class="itn-price__n"><?= t($pr['note']) ?></p>
          <ul><?php foreach ($pr['items'] ?? [] as $it): ?><li><?= tick(18, 9) ?><?= t($it) ?></li><?php endforeach; ?></ul>
          <?php if (!empty($in['trial_url'])): ?><a class="btn itn__btn-lime itn-price__cta"<?= link_attrs($in['trial_url']) ?>><?= t($in['trial_cta']) ?></a><?php endif; ?>
          <?php if (!empty($pr['url'])): ?><a class="itn__link itn-price__sim"<?= link_attrs($pr['url']) ?>><?= t($pr['cta']) ?> ↗</a><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (trim((string) ($in['france'] ?? '')) !== ''): ?><p class="wrap itn-fr"><?= fr_flag() ?><?= t($in['france']) ?></p><?php endif; ?>
  <div class="sol-marquee itn-marquee" aria-label="Ce qu'on peut afficher">
    <div class="sol-marquee__track"><?php $media = $in['media']; foreach (array_merge($media, $media) as $i => $m): ?><span<?= $i >= count($media) ? ' aria-hidden="true"' : '' ?>><?= t($m) ?></span><?php endforeach; ?></div>
  </div>
</section>
