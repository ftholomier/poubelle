<?php
/** @var array $g @var array $s */
$f = $g['footer'] ?? [];
$zones = LD\Content::get('zones')['cities'] ?? [];
?>
<footer class="ftr">
  <div class="ftr__glow" aria-hidden="true"></div>
  <div class="ftr__in">
    <p class="ftr__slogan"><?= t($f['slogan'] ?? '') ?> <span><?= t($f['slogan_accent'] ?? '') ?></span></p>
    <div class="ftr__cols">
      <div class="ftr__col">
        <img src="<?= e($s['site']['logo_white'] ?? '/assets/img/logo-blanc.png') ?>" alt="<?= e($s['site']['name'] ?? 'LE-DIGITAL.com') ?>" width="128" height="30" loading="lazy" class="ftr__logo">
        <span><?= e($s['site']['owner'] ?? 'Frédéric Tholomier') ?></span>
        <a class="ftr__phone" href="<?= e(tel_href()) ?>"><?= e(phone_label()) ?></a>
      </div>
      <div class="ftr__col">
        <span class="ftr__h"><?= t($f['zones_title'] ?? 'Zones') ?></span>
        <?php foreach ($zones as $z): ?><a href="/zones/<?= e($z['slug']) ?>"><?= e($z['name']) ?></a><?php endforeach; ?>
        <span><?= t($f['remote'] ?? '') ?></span>
      </div>
      <div class="ftr__col">
        <span class="ftr__h"><?= t($f['solutions_title'] ?? 'Mes solutions') ?></span>
        <?php foreach ($f['solutions'] ?? [] as $l): ?><a href="<?= e($l['href']) ?>"><?= t($l['label']) ?></a><?php endforeach; ?>
      </div>
      <div class="ftr__col">
        <span class="ftr__h"><?= t($f['links_title'] ?? 'Liens') ?></span>
        <?php foreach ($f['links'] ?? [] as $l): ?><a href="<?= e($l['href']) ?>"><?= t($l['label']) ?></a><?php endforeach; ?>
        <a<?= link_attrs(linkedin_url()) ?>><?= t($f['linkedin_label'] ?? 'LinkedIn') ?></a>
      </div>
    </div>
  </div>
</footer>
