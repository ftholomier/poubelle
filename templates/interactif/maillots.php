<?php
/** Comparateur de maillots (maquette « Maillots »). Variables : $eras */
$n = count($eras);
$l = min(2, max(0, $n - 2));
$r = max(0, $n - 1);
$short = fn (array $e) => str_starts_with((string) $e['era'], '19') ? "'" . substr((string) $e['era'], 2) : (string) $e['era'];
?>
<section class="wrap jers" data-jerseys>
  <div class="stack" style="gap:12px">
    <span class="eyebrow eyebrow--lg"><?= e(t('Symboles · Le comparateur')) ?></span>
    <h1 class="h-xl jers__title"><?= e(t('Un maillot,')) ?><br><span class="blue"><?= e(t('deux époques.')) ?></span></h1>
  </div>
  <div class="jers__pickers">
    <div class="stack" style="gap:10px">
      <span class="jers__side"><?= e(t('À gauche')) ?></span>
      <div class="jers__chips" data-side="l"><?php foreach ($eras as $i => $e): ?><button type="button" class="jchip<?= $i === $l ? ' is-on' : '' ?>" data-i="<?= $i ?>"><?= e($short($e)) ?></button><?php endforeach; ?></div>
    </div>
    <div class="stack" style="gap:10px;align-items:flex-end">
      <span class="jers__side"><?= e(t('À droite')) ?></span>
      <div class="jers__chips" data-side="r" style="justify-content:flex-end"><?php foreach ($eras as $i => $e): ?><button type="button" class="jchip<?= $i === $r ? ' is-on' : '' ?>" data-i="<?= $i ?>"><?= e($short($e)) ?></button><?php endforeach; ?></div>
    </div>
  </div>
  <script type="application/json" data-jerseys-data><?= json_encode(array_map(fn ($e) => ['label' => $e['label'], 'image' => $e['image'] ? img($e['image'], 1200) : null, 'text' => strip_tags((string) ($e['text'] ?? ''))], $eras), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <div class="jers__box" data-jbox data-l="<?= $l ?>" data-r="<?= $r ?>">
    <div class="jers__img jers__img--r" data-jimg="r"></div>
    <div class="jers__img jers__img--l" data-jimg="l"></div>
    <span class="jers__tag jers__tag--l" data-jtag="l"></span>
    <span class="jers__tag jers__tag--r" data-jtag="r"></span>
    <span class="jers__line" data-jline></span>
    <button type="button" class="jers__handle" data-jhandle aria-label="<?= e(t('Faire glisser pour comparer')) ?>" role="slider" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50">↔</button>
  </div>
  <div class="jers__texts"><p data-jtext="l"></p><p data-jtext="r" style="text-align:right"></p></div>
  <span class="muted italic"><?= e(t('Glissez la poignée jaune (ou utilisez les flèches du clavier). Les photos de maillots sont ajoutées par les historiens du musée.')) ?></span>
</section>
