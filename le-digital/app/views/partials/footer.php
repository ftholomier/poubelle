<?php
/** @var array $g @var array $s */
$f = $g['footer'] ?? [];
$zones = LD\Content::get('zones')['cities'] ?? [];
$fr = $f['made_in_france'] ?? [];
// Carrousel d'avis collé dans le back-office (Avis › Widgets) ; à défaut, preuve sociale native (note Google, extraits d'avis).
$carousel = LD\Reviews::carouselPlace(LD\Http::path());
?>
<?php if ($carousel === 'before'): ?>
<section class="rv-band" aria-label="Avis clients">
  <div class="rv-band__in"><div class="rv-widget rv-widget--carousel"><?= LD\Reviews::carouselCode() ?></div></div>
</section>
<?php endif; ?>
<footer class="ftr">
  <div class="ftr__glow" aria-hidden="true"></div>
  <div class="ftr__in">
    <?php if ($carousel === 'inside'): ?>
      <div class="rv-widget rv-widget--carousel rv-widget--dark"><?= LD\Reviews::carouselCode() ?></div>
    <?php elseif (LD\Reviews::carouselCode() === ''): ?>
      <?= LD\View::partial('footer-proof') ?>
    <?php endif; ?>
    <?php if (trim((string) ($fr['title'] ?? '')) !== ''): ?>
      <div class="ftr__fr">
        <div class="ftr__fr-head"><?= fr_flag() ?><span><?= t($fr['title']) ?></span></div>
        <?php if (trim((string) ($fr['text'] ?? '')) !== ''): ?><p class="ftr__fr-text"><?= t($fr['text']) ?></p><?php endif; ?>
        <?php if (!empty($fr['items'])): ?><ul class="ftr__fr-items"><?php foreach ($fr['items'] as $it): ?><li><?= tick(18, 9) ?><?= t($it) ?></li><?php endforeach; ?></ul><?php endif; ?>
      </div>
    <?php endif; ?>
    <p class="ftr__slogan"><?= t($f['slogan'] ?? '') ?> <span><?= t($f['slogan_accent'] ?? '') ?></span></p>
    <div class="ftr__cols">
      <div class="ftr__col">
        <img src="<?= e(site_logo(true)) ?>" alt="<?= e($s['site']['name'] ?? 'LE-DIGITAL.com') ?>" width="206" height="48" loading="lazy" class="ftr__logo">
        <span><?= e($s['site']['owner'] ?? 'Frédéric Tholomier') ?></span>
        <?php if (phone_label() !== ''): ?><a class="ftr__phone" href="<?= e(tel_href()) ?>"><?= e(phone_label()) ?></a><?php endif; ?>
      </div>
      <div class="ftr__col">
        <span class="ftr__h"><?= t($f['zones_title'] ?? 'Zones') ?></span>
        <?php foreach ($zones as $z): ?><a href="/zones/<?= e($z['slug']) ?>"><?= e($z['name']) ?></a><?php endforeach; ?>
        <?php if (trim((string) ($f['remote'] ?? '')) !== ''): ?><a href="/partout-en-france"><?= t($f['remote']) ?></a><?php endif; ?>
      </div>
      <div class="ftr__col">
        <span class="ftr__h"><?= t($f['links_title'] ?? 'Liens') ?></span>
        <?php foreach ($f['links'] ?? [] as $l): ?><a href="<?= e($l['href']) ?>"><?= t($l['label']) ?></a><?php endforeach; ?>
        <a<?= link_attrs(linkedin_url()) ?>><?= t($f['linkedin_label'] ?? 'LinkedIn') ?></a>
      </div>
    </div>
    <p class="ftr__legal">© <?= date('Y') ?> <?= e($s['site']['name'] ?? 'LE-DIGITAL.com') ?> · <?= e($s['site']['owner'] ?? 'Frédéric Tholomier') ?><?php if (trim((string) ($fr['copyright'] ?? '')) !== ''): ?> · <span class="fr-chip"><?= t($fr['copyright']) ?> <?= fr_flag() ?></span><?php endif; ?></p>
  </div>
</footer>
