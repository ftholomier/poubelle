<?php
/** Audience. Variables : $series, $top, $en */
$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$max = max(1, max($series ?: [0]));
$last30 = array_slice($series, -30, null, true);
?>
<div class="kpis">
  <div class="kpi"><b><?= $fmt(array_sum($last30)) ?></b><span>pages vues · 30 jours</span><small><?= $fmt(array_sum($series)) ?> sur 90 jours</small></div>
  <div class="kpi"><b><?= $fmt(round(array_sum($last30) / 30)) ?></b><span>pages vues par jour</span><small>moyenne 30 jours</small></div>
  <div class="kpi"><b><?= str_replace('.', ',', (string) $en) ?> %</b><span>en anglais</span><small>version /en/</small></div>
</div>
<div class="card card--pad">
  <h2 class="card__t">Pages vues par jour · 90 jours</h2>
  <div class="spark" style="height:180px"><?php foreach ($series as $d => $n): ?><i style="height:<?= max(1, round($n / $max * 100)) ?>%" data-t="<?= e(date('d/m', strtotime($d))) ?> · <?= $fmt($n) ?>"></i><?php endforeach; ?></div>
  <span class="small muted">Mesure interne sans cookie ni adresse IP (exemptée de consentement). Les robots d’indexation sont exclus.</span>
</div>
<div class="card">
  <div class="card__head"><h2 class="card__t">Pages les plus vues · 30 jours</h2></div>
  <?php $i = 0; foreach ($top as $p => $n): $i++; ?>
    <div class="card__row" style="grid-template-columns:40px minmax(0,1fr) 90px"><b class="d" style="color:var(--blue)"><?= $i ?></b><a href="<?= e($p) ?>" target="_blank" rel="noopener"><?= e($p) ?></a><span class="d right" style="font-weight:800"><?= $fmt($n) ?></span></div>
  <?php endforeach; ?>
  <?php if (!$top): ?><div class="card__body muted">Pas encore de données : elles s’accumulent dès l’ouverture du site.</div><?php endif; ?>
</div>
