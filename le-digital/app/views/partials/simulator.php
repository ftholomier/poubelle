<?php
$sim = LD\Content::get('global')['simulator'];
$tasks = $sim['tasks'] ?? [];
$h = array_map(static fn($t) => (float) ($t['default'] ?? 0), $tasks);
$total = array_sum($h);
$saved = 0.0;
foreach ($tasks as $i => $t) {
    $saved += $h[$i] * (float) $t['rate'];
}
$r1 = static fn(float $n) => round($n, 1);
$fmt = static fn(float $n) => str_replace('.', ',', (string) $r1($n));
$wpy = (int) ($sim['weeks_per_year'] ?? 45);
$hpd = max(1, (int) ($sim['hours_per_day'] ?? 7));
$cfg = ['tasks' => array_map(static fn($t) => ['short' => $t['short'] ?? $t['label'], 'rate' => (float) $t['rate'], 'def' => (float) $t['default']], $tasks), 'wpy' => $wpy, 'hpd' => $hpd];
?>
<div class="sim" data-sim="<?= json_attr($cfg) ?>">
  <div class="sim__glow" aria-hidden="true"></div>
  <div class="sim__l">
    <div class="sim__head">
      <span class="kicker kicker--lt kicker--sm"><?= t($sim['kicker']) ?></span>
      <h3 class="sim__title"><?= t($sim['title']) ?></h3>
    </div>
    <?php foreach ($tasks as $i => $tk): ?>
      <label class="sim__task">
        <span class="sim__row"><span><?= t($tk['label']) ?></span><b><output data-sim-out="<?= $i ?>"><?= e($fmt($h[$i])) ?></output>&nbsp;h</b></span>
        <input type="range" min="0" max="12" step="0.5" value="<?= e((string) $h[$i]) ?>" data-sim-in="<?= $i ?>" aria-label="<?= e($tk['label']) ?> (heures par semaine)">
      </label>
    <?php endforeach; ?>
  </div>
  <div class="sim__r">
    <div class="sim__res">
      <span class="sim__intro"><?= t($sim['result_intro']) ?></span>
      <span class="sim__big"><span class="sim__num" data-sim-saved><?= e($fmt($saved)) ?></span><span class="sim__unit"><?= t($sim['unit']) ?></span></span>
      <span class="sim__year">soit <b><span data-sim-year><?= (int) round($saved * $wpy) ?></span>&nbsp;h par an</b>, environ <span data-sim-days><?= (int) round($saved * $wpy / $hpd) ?></span> journées de travail.</span>
    </div>
    <div class="sim__bars">
      <div class="sim__bar"><span class="sim__bl"><span><?= t($sim['today_label']) ?></span><span><span data-sim-total><?= e((string) $r1($total)) ?></span>&nbsp;h</span></span><div class="sim__track"><div class="sim__fill"></div></div></div>
      <div class="sim__bar"><span class="sim__bl sim__bl--lt"><span><?= t($sim['after_label']) ?></span><span><span data-sim-after><?= e((string) $r1($total - $saved)) ?></span>&nbsp;h</span></span><div class="sim__track"><div class="sim__fill sim__fill--red" data-sim-pct style="width:<?= $total ? round(($total - $saved) / $total * 100, 2) : 0 ?>%"></div></div></div>
    </div>
    <a class="btn btn-white btn-sim" href="/on-prend-rdv"><?= t($sim['cta']) ?></a>
    <p class="sim__note"><?= t($sim['note']) ?></p>
  </div>
</div>
