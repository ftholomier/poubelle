<?php
/** @var array $g @var array $s */
$f = $g['footer'] ?? [];
$zones = LD\Content::get('zones')['cities'] ?? [];
$fr = $f['made_in_france'] ?? [];
// Carrousel d'avis collé dans le back-office (Avis › Widgets) ; à défaut, preuve sociale native (note Google, extraits d'avis).
$carousel = LD\Reviews::carouselPlace(LD\Http::path());
// Avec la gestion des cookies, le carrousel (service tiers) n'apparaît qu'après accord ; sinon la preuve sociale native le remplace.
$gated = $carousel !== '' && LD\Consent::enabled();
?>
<?php if ($carousel === 'before'): ?>
<section class="rv-band" aria-label="Avis clients"<?= $gated ? ' data-consent-show="tiers" hidden' : '' ?>>
  <div class="rv-band__in"><div class="rv-widget rv-widget--carousel"><?= LD\Consent::wrap('tiers', LD\Reviews::carouselCode()) ?></div></div>
</section>
<?php endif; ?>
<footer class="ftr">
  <div class="ftr__glow" aria-hidden="true"></div>
  <div class="ftr__in">
    <?php if ($carousel === 'inside'): ?>
      <div class="rv-widget rv-widget--carousel rv-widget--dark"<?= $gated ? ' data-consent-show="tiers" hidden' : '' ?>><?= LD\Consent::wrap('tiers', LD\Reviews::carouselCode()) ?></div>
    <?php endif; ?>
    <?php if (LD\Reviews::carouselCode() === ''): ?>
      <?= LD\View::partial('footer-proof') ?>
    <?php elseif ($gated): ?>
      <div data-consent-hide="tiers"><?= LD\View::partial('footer-proof') ?></div>
    <?php endif; ?>
    <?php if (trim((string) ($fr['title'] ?? '')) !== ''): ?>
      <div class="ftr__fr">
        <div class="ftr__fr-head"><?= fr_flag() ?><span><?= t($fr['title']) ?></span></div>
        <?php if (trim((string) ($fr['text'] ?? '')) !== ''): ?><p class="ftr__fr-text"><?= t($fr['text']) ?></p><?php endif; ?>
        <?php if (!empty($fr['items'])): ?><ul class="ftr__fr-items"><?php foreach ($fr['items'] as $it): ?><li><?= tick(18, 9) ?><?= t($it) ?></li><?php endforeach; ?></ul><?php endif; ?>
      </div>
    <?php endif; ?>
    <p class="ftr__slogan"><?= t($f['slogan'] ?? '') ?> <span><?= t($f['slogan_accent'] ?? '') ?></span></p>
    <?php
    $arrow = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
    $pin = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0119 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>';
    $linkedin = linkedin_url();
    ?>
    <div class="ftr__grid">
      <div class="ftr__brand">
        <img src="<?= e(site_logo(true)) ?>" alt="<?= e($s['site']['name'] ?? 'LE-DIGITAL.com') ?>" width="206" height="48" loading="lazy" class="ftr__logo">
        <?php if (trim((string) ($f['about'] ?? '')) !== ''): ?><p class="ftr__about"><?= t($f['about']) ?></p><?php else: ?><span><?= e($s['site']['owner'] ?? 'Frédéric Tholomier') ?></span><?php endif; ?>
        <div class="ftr__acts">
          <a class="ftr__cta" href="/on-prend-rdv"><?= t($g['header']['cta'] ?? 'On prend RdV ?') ?><?= $arrow ?></a>
          <?php if ($linkedin !== '#'): ?><a class="ftr__so" href="<?= e($linkedin) ?>" target="_blank" rel="noopener" aria-label="<?= e($f['linkedin_label'] ?? 'LinkedIn') ?>"><svg width="19" height="19" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.98 3.5a2.5 2.5 0 11-.02 5 2.5 2.5 0 01.02-5zM3 9h4v12H3zM9 9h3.8v1.7h.05c.53-1 1.84-2.05 3.78-2.05 4.05 0 4.8 2.66 4.8 6.13V21h-4v-5.5c0-1.3-.03-3-1.83-3-1.84 0-2.12 1.43-2.12 2.9V21H9z"/></svg></a><?php endif; ?>
        </div>
      </div>
      <nav class="ftr__nav" aria-label="<?= e(strip_tags((string) ($f['zones_title'] ?? 'Zones'))) ?>">
        <span class="ftr__h"><?= t($f['zones_title'] ?? 'Zones') ?></span>
        <div class="ftr__chips">
          <?php foreach ($zones as $z): ?><a class="ftr__chip" href="/zones/<?= e($z['slug']) ?>"><?= $pin ?><?= e($z['name']) ?></a><?php endforeach; ?>
          <?php if (trim((string) ($f['remote'] ?? '')) !== ''): ?><a class="ftr__chip ftr__chip--fr" href="/partout-en-france"><?= fr_flag() ?><?= t($f['remote']) ?></a><?php endif; ?>
        </div>
      </nav>
      <nav class="ftr__nav" aria-label="<?= e(strip_tags((string) ($f['links_title'] ?? 'Liens'))) ?>">
        <span class="ftr__h"><?= t($f['links_title'] ?? 'Liens') ?></span>
        <ul class="ftr__links">
          <?php foreach ($f['links'] ?? [] as $l): ?><li><a href="<?= e($l['href']) ?>"><span><?= t($l['label']) ?></span><?= $arrow ?></a></li><?php endforeach; ?>
          <?php if ($linkedin !== '#'): ?><li><a<?= link_attrs($linkedin) ?>><span><?= t($f['linkedin_label'] ?? 'LinkedIn') ?></span><?= $arrow ?></a></li><?php endif; ?>
          <?php if (LD\Consent::enabled()): ?><li><button type="button" class="ftr__linkbtn" data-cookie-open><span><?= t($g['cookies']['footer_link'] ?? 'Gestion des cookies') ?></span><?= $arrow ?></button></li><?php endif; ?>
        </ul>
      </nav>
    </div>
    <div class="ftr__bottom">
      <p class="ftr__legal">© <?= date('Y') ?> <?= e($s['site']['name'] ?? 'LE-DIGITAL.com') ?> · <?= e($s['site']['owner'] ?? 'Frédéric Tholomier') ?><?php if (trim((string) ($fr['copyright'] ?? '')) !== ''): ?> · <span class="fr-chip"><?= t($fr['copyright']) ?> <?= fr_flag() ?></span><?php endif; ?></p>
      <a class="ftr__top" href="#contenu" data-to-top>Retour en haut <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"/></svg></span></a>
    </div>
    <?php $mark = (string) ($s['site']['name'] ?? 'LE-DIGITAL.com'); $dot = mb_strrpos($mark, '.'); ?>
    <div class="ftr__mark" aria-hidden="true" data-ftr-mark><?= $dot !== false ? e(mb_substr($mark, 0, $dot)) . '<small>' . e(mb_substr($mark, $dot)) . '</small>' : e($mark) ?></div>
  </div>
</footer>
