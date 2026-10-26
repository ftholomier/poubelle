<?php
/** Pop-up de sortie : proposée une fois, au moment où le visiteur s'apprête à quitter le site. @var array $g @var array $s */
$x = $g['exit_popup'];
$duration = (int) ($s['booking']['duration'] ?? 45);
$fill = static fn(string $v) => str_replace('{duration}', (string) $duration, $v);
$cfg = [
    'delay'   => max(0, (int) ($x['delay_seconds'] ?? 8)),
    'snooze'  => max(1, (int) ($x['snooze_days'] ?? 3)),
    'simText' => (string) ($x['sim_text'] ?? ''),
];
?>
<div class="xp" id="exit-popup" data-exit="<?= json_attr($cfg) ?>" hidden>
  <div class="xp__backdrop" data-xp-close></div>
  <section class="xp__card" role="dialog" aria-modal="true" aria-labelledby="xp-title" aria-describedby="xp-text" tabindex="-1">
    <button type="button" class="xp__close" data-xp-close aria-label="Fermer"><?= icon('close', 14, 'currentColor', 2.2) ?></button>
    <div class="xp__media" aria-hidden="true">
      <?= img_tag($x['image'] ?? '', '', ['sizes' => '360px']) ?>
      <?php if (trim((string) ($x['bubble'] ?? '')) !== ''): ?><p class="xp__bubble"><small>Fred</small><?= t($x['bubble']) ?></p><?php endif; ?>
    </div>
    <div class="xp__body">
      <div class="xp__mini" aria-hidden="true"><img src="<?= e(site_avatar()) ?>" alt="" width="44" height="44"><span><b>Fred</b><i></i>dispo pour en parler</span></div>
      <span class="xp__kicker"><?= t($x['kicker']) ?></span>
      <h2 class="xp__title" id="xp-title"><?= t($fill($x['title'])) ?><?php if (trim((string) ($x['title_accent'] ?? '')) !== ''): ?> <span><?= t($fill($x['title_accent'])) ?></span><?php endif; ?></h2>
      <p class="xp__text" id="xp-text" data-xp-text><?= t($x['text']) ?></p>
      <?php if (!empty($x['items'])): ?><ul class="xp__ticks"><?php foreach ($x['items'] as $it): ?><li><?= tick(18, 9) ?><?= t($it) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <a class="xp__cta" href="/on-prend-rdv#reserver" data-xp-go><?= t($x['cta']) ?> <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
      <form class="xp__cb" data-xp-cb novalidate>
        <label for="xp-phone"><?= t($x['callback_label']) ?></label>
        <div class="xp__cb-row">
          <input id="xp-phone" name="phone" type="tel" maxlength="30" autocomplete="tel" placeholder="<?= e($x['callback_placeholder'] ?? '') ?>">
          <button type="submit"><?= t($x['callback_cta']) ?></button>
        </div>
        <input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
        <p class="xp__err" role="alert" hidden></p>
      </form>
      <p class="xp__done" role="status" hidden><?= t($x['callback_done']) ?></p>
      <div class="xp__foot">
        <?php if (!empty($g['dock']['enabled'])): ?><button type="button" class="xp__voice" data-xp-voice><span aria-hidden="true"><?= icon('mic', 14, '#fff', 2.4) ?></span><?= t($x['voice_cta']) ?></button><?php endif; ?>
        <button type="button" class="xp__no" data-xp-close><?= t($x['dismiss']) ?></button>
      </div>
    </div>
  </section>
</div>
