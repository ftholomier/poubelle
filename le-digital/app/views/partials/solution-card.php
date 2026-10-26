<?php
/** Carte d'une solution (mosaïque « Mes solutions », « Découvrir aussi »). @var array $so @var int $i */
$i ??= 0;
$variant = $so['kind'] === 'intensiti' ? 'itn' : (($so['theme'] ?? '') === 'sombre' ? 'dark' : 'light');
$tag = trim(trim((string) ($so['tagline'] ?? '')) . ' ' . trim((string) ($so['tagline_accent'] ?? '')));
$about = trim((string) (($so['short'] ?? '') ?: ($so['text'] ?? '')));
// Phrase courte qui répète l'accroche : on montre plutôt la présentation.
if ($tag !== '' && str_starts_with(mb_strtolower($about), mb_strtolower(mb_substr($tag, 0, 24))) && trim((string) ($so['text'] ?? '')) !== '') {
    $about = trim((string) $so['text']);
}
$tags = array_slice((array) ($so['strengths'] ?? []), 0, 3);
?>
<a class="solc solc--<?= $variant ?>" href="<?= e(LD\Solutions::url($so)) ?>" data-sol="<?= e($so['id']) ?>" data-reveal data-delay="<?= min(360, $i * 90) ?>">
  <div class="solc__img">
    <?= img_tag($so['image'] ?? '', 'Aperçu de ' . $so['name'], ['sizes' => '(max-width: 760px) 92vw, 600px']) ?: '<span class="solc__ph">' . e($so['name']) . '</span>' ?>
    <?php if (trim((string) ($so['price'] ?? '')) !== ''): ?><span class="solc__badge"><?= t($so['price']) ?></span><?php endif; ?>
  </div>
  <div class="solc__body">
    <div class="solc__top">
      <?php if (!empty($so['logo'])): ?><img class="solc__logo" src="<?= e($so['logo']) ?>" alt="<?= e($so['name']) ?>" loading="lazy"><?php else: ?><span class="solc__name"><?= t($so['name']) ?></span><?php endif; ?>
    </div>
    <?php if ($tag !== ''): ?><h2 class="solc__t"><?= t($tag) ?></h2><?php endif; ?>
    <?php if ($about !== ''): ?><p class="solc__x"><?= t(mb_strimwidth($about, 0, 190, '…')) ?></p><?php endif; ?>
    <?php if ($tags): ?><div class="solc__tags"><?php foreach ($tags as $tg): ?><span><?= t($tg) ?></span><?php endforeach; ?></div><?php endif; ?>
    <span class="solc__more">Découvrir <?= t($so['name']) ?> <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
  </div>
</a>
