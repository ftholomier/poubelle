<?php
/** @var array $g @var array $s @var string $active */
$nav = $g['header']['nav'] ?? [];
$cta = $g['header']['cta'] ?? 'On prend RdV ?';
$phone = trim((string) ($s['contact']['phone'] ?? ''));
$isActive = static fn(array $it) => ($it['key'] ?? '') === $active && $active !== '';
$arrow = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
?>
<header class="hdr" id="hdr" data-hdr>
  <span class="hdr__progress" aria-hidden="true"></span>
  <div class="hdr__in">
    <a class="hdr__logo" href="/" aria-label="<?= e($s['site']['name'] ?? 'LE-DIGITAL.com') ?> — accueil">
      <img src="<?= e(site_logo()) ?>" alt="<?= e($s['site']['name'] ?? 'LE-DIGITAL.com') ?>" width="189" height="44">
    </a>
    <nav class="hdr__nav" aria-label="Navigation principale" data-hdr-nav>
      <span class="hdr__ind" aria-hidden="true"></span>
      <?php foreach ($nav as $i => $it): ?>
        <a href="<?= e($it['href']) ?>" style="--i:<?= (int) $i ?>"<?= $isActive($it) ? ' class="is-active" aria-current="page"' : '' ?>><?= t($it['label']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="hdr__actions">
      <a class="hdr__cta" href="/on-prend-rdv"><span><?= t($cta) ?></span><span class="hdr__arr"><?= $arrow ?></span></a>
      <button class="hdr__burger" type="button" aria-label="Menu" aria-expanded="false" aria-controls="hdr-menu" data-menu-toggle><span></span><span></span></button>
    </div>
  </div>
  <nav class="hdr__menu" id="hdr-menu" aria-label="Menu mobile" hidden>
    <div class="hdr__menu-list">
      <?php foreach ($nav as $i => $it): ?>
        <a href="<?= e($it['href']) ?>" style="--i:<?= (int) $i ?>"<?= $isActive($it) ? ' class="is-active" aria-current="page"' : '' ?>><small><?= sprintf('%02d', $i + 1) ?></small><span><?= t($it['label']) ?></span><?= $arrow ?></a>
      <?php endforeach; ?>
    </div>
    <div class="hdr__menu-foot" style="--i:<?= count($nav) ?>">
      <a class="hdr__menu-cta" href="/on-prend-rdv"><?= t($cta) ?> <?= $arrow ?></a>
      <?php if ($phone !== ''): ?><a class="hdr__menu-tel" href="<?= e(tel_href()) ?>"><?= icon('phone', 18, '#cc1517') ?> <?= e($phone) ?></a><?php endif; ?>
      <?php if (trim((string) ($g['header']['made_in_france'] ?? '')) !== ''): ?><span class="hdr__menu-fr"><?= fr_flag() ?><?= t($g['header']['made_in_france']) ?></span><?php endif; ?>
    </div>
  </nav>
</header>
