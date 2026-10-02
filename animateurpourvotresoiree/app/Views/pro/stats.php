<?php
use App\Services\Chart;

/** @var array $pro @var array $series @var int $days @var ?array $rank */
$tot = static fn (string $k): int => array_sum(array_column($series, $k));
$views = $tot('pro_view');
$contacts = $tot('phone') + $tot('site') + $tot('message');
?>
<div class="pro-head">
  <div><p class="mono muted small">Statistiques</p><h1 class="h2">Votre <span class="serif c-coral">audience</span></h1></div>
  <nav class="seg" aria-label="Période">
    <?php foreach ([7 => '7 jours', 30 => '30 jours', 90 => '3 mois', 365 => '1 an'] as $d => $label): ?>
      <a href="?jours=<?= $d ?>"<?= $days === $d ? ' class="on" aria-current="true"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
</div>
<div class="kpis mt-2">
  <div class="kpi"><span>Vues de la fiche</span><b><?= nf($views) ?></b></div>
  <div class="kpi"><span>Numéros affichés</span><b><?= nf($tot('phone')) ?></b></div>
  <div class="kpi"><span>Clics vers votre site / réseaux</span><b><?= nf($tot('site')) ?></b></div>
  <div class="kpi"><span>Messages reçus</span><b><?= nf($tot('message')) ?></b></div>
  <div class="kpi"><span>Demandes de devis reçues</span><b><?= nf($tot('request')) ?></b></div>
  <div class="kpi"><span>Taux de contact</span><b><?= $views ? number_format($contacts / $views * 100, 1, ',', '') . ' %' : '–' ?></b></div>
</div>
<div class="box mt-3">
  <h2>Vues de la fiche</h2>
  <?php if ($days <= 90): ?>
    <?= Chart::bars(array_map(static fn ($d) => $d['pro_view'], $series), ['height' => 200, 'title' => 'Vues de la fiche']) ?>
  <?php else: ?>
    <?php $byMonth = []; foreach ($series as $d => $v) { $m = substr($d, 0, 7); $byMonth[$m] = ($byMonth[$m] ?? 0) + $v['pro_view']; } ?>
    <?= Chart::bars($byMonth, ['height' => 200, 'title' => 'Vues de la fiche par mois']) ?>
  <?php endif; ?>
</div>
<div class="box mt-3">
  <h2>Contacts générés</h2>
  <?php
  $agg = static function (string $k) use ($series, $days): array {
      if ($days <= 90) {
          return array_map(static fn ($d) => $d[$k], $series);
      }
      $out = [];
      foreach ($series as $d => $v) {
          $m = substr($d, 0, 7);
          $out[$m] = ($out[$m] ?? 0) + $v[$k];
      }
      return $out;
  };
  ?>
  <?= Chart::lines(['Numéros affichés' => $agg('phone'), 'Clics site' => $agg('site'), 'Messages' => $agg('message'), 'Demandes' => $agg('request')], ['height' => 220, 'title' => 'Contacts générés']) ?>
</div>
<?php if ($rank): ?>
<div class="box box-cream mt-3">
  <h2>Votre position</h2>
  <p>Votre fiche est <strong>n° <?= (int) $rank['pos'] ?></strong> sur <?= (int) $rank['of'] ?> en nombre de vues parmi les « <?= e($rank['cat']) ?> » du département <?= e($rank['dep']) ?> (depuis l'ouverture du nouveau site).</p>
  <p class="small muted">Le classement des résultats tient compte de la complétude de la fiche, des avis et de la proximité : complétez votre fiche pour gagner des places !</p>
</div>
<?php endif; ?>
