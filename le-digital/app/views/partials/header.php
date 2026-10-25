<?php
/** @var array $g @var array $s @var string $active */
$nav = $g['header']['nav'] ?? [];
?>
<header class="hdr" id="hdr">
  <div class="hdr__in">
    <a class="hdr__logo" href="/" aria-label="<?= e($s['site']['name'] ?? 'LE-DIGITAL.com') ?> — accueil">
      <img src="<?= e($s['site']['logo'] ?? '/assets/img/logo.png') ?>" alt="<?= e($s['site']['name'] ?? 'LE-DIGITAL.com') ?>" width="146" height="34">
    </a>
    <nav class="hdr__nav" aria-label="Navigation principale">
      <?php foreach ($nav as $it): ?>
        <a href="<?= e($it['href']) ?>"<?= ($it['key'] ?? '') === $active && $active !== '' ? ' class="is-active" aria-current="page"' : '' ?>><?= t($it['label']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="hdr__actions">
      <a class="hdr__cta" href="/on-prend-rdv"><?= t($g['header']['cta'] ?? 'On prend RdV ?') ?></a>
      <button class="hdr__burger" type="button" aria-label="Menu" aria-expanded="false" aria-controls="hdr-menu" data-menu-toggle><?= icon('menu', 20, '#161922') ?></button>
    </div>
  </div>
  <nav class="hdr__menu" id="hdr-menu" aria-label="Menu mobile" hidden>
    <?php foreach ($nav as $it): ?>
      <a href="<?= e($it['href']) ?>"<?= ($it['key'] ?? '') === $active && $active !== '' ? ' class="is-active" aria-current="page"' : '' ?>><?= t($it['label']) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
