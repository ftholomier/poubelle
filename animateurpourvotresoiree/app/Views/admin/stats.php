<?php
use App\Core\Url;
use App\Services\Chart;
use App\Services\Geo;
use App\Services\Stats;

/** @var int $days @var array $metrics */
$agg = static function (array $series) use ($days): array {
    if ($days <= 90) {
        return $series;
    }
    $out = [];
    foreach ($series as $d => $v) {
        $out[substr($d, 0, 7)] = ($out[substr($d, 0, 7)] ?? 0) + $v;
    }
    return $out;
};
?>
<div class="adm-head">
  <div><h1>Statistiques <span class="serif">détaillées</span></h1><p>Mesure d'audience interne, sans cookie (les robots sont exclus).</p></div>
  <nav class="seg"><?php foreach ([7 => '7 j', 30 => '30 j', 90 => '3 mois', 365 => '1 an'] as $d => $l): ?><a href="?jours=<?= $d ?>"<?= $days === $d ? ' class="on"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></nav>
</div>
<div class="kpis">
  <?php foreach (['uniq', 'pv', 'pro_view', 'phone', 'site', 'devis', 'message', 'search', 'register', 'review', 'chat', 'bots'] as $m): ?>
    <div class="kpi"><span><?= e(Stats::label($m)) ?></span><b><?= nf(array_sum($metrics[$m])) ?></b><?= App\Services\Chart::spark(array_values($metrics[$m])) ?></div>
  <?php endforeach; ?>
</div>
<div class="box mt-3"><h2>Audience</h2><?= Chart::lines(['Visiteurs uniques' => $agg($metrics['uniq']), 'Pages vues' => $agg($metrics['pv']), 'Fiches consultées' => $agg($metrics['pro_view'])], ['height' => 240]) ?></div>
<div class="box mt-3"><h2>Contacts générés pour les pros</h2><?= Chart::lines(['Demandes de devis' => $agg($metrics['devis']), 'Messages' => $agg($metrics['message']), 'Numéros affichés' => $agg($metrics['phone']), 'Clics vers les sites' => $agg($metrics['site'])], ['height' => 240]) ?></div>
<div class="adm-grid mt-3">
  <div class="box"><h2>Fiches les plus vues (depuis le lancement)</h2>
    <ul class="list-rows"><?php foreach ($topPros as $id => $p): ?><li><a href="<?= e(Url::admin('pros/' . $id)) ?>"><?= e($p['name']) ?></a><span class="muted"><?= e($p['city']) ?></span><b><?= nf($p['views']) ?></b></li><?php endforeach; ?></ul></div>
  <div class="box"><h2>Recherches des visiteurs (ce mois)</h2>
    <?php if (!$searches): ?><p class="muted">Aucune recherche enregistrée.</p><?php else: ?><ul class="list-rows"><?php foreach ($searches as $q => $n): ?><li><span><?= e((string) $q) ?></span><b><?= (int) $n ?></b></li><?php endforeach; ?></ul><?php endif; ?></div>
</div>
<div class="adm-grid-3 mt-3">
  <div class="box"><h2>Demandes par département</h2><ul class="list-rows"><?php foreach ($byDep as $d => $n): ?><li><span><?= e((Geo::dep((string) $d)['name'] ?? $d) . ' (' . $d . ')') ?></span><b><?= (int) $n ?></b></li><?php endforeach; ?><?php if (!$byDep): ?><li class="muted">Aucune demande sur la période.</li><?php endif; ?></ul></div>
  <div class="box"><h2>Demandes par occasion</h2><?= $byType ? Chart::donut($byType, ['unit' => 'demandes']) : '<p class="muted">—</p>' ?></div>
  <div class="box"><h2>Métiers demandés</h2><ul class="list-rows"><?php foreach (array_slice($byCat, 0, 12, true) as $c => $n): ?><li><span><?= e((string) $c) ?></span><b><?= (int) $n ?></b></li><?php endforeach; ?><?php if (!$byCat): ?><li class="muted">—</li><?php endif; ?></ul></div>
</div>
<div class="adm-grid mt-3">
  <div class="box"><h2>Historique mensuel des demandes de devis</h2><?= Chart::bars(array_slice($monthly['requests'], -60, null, true), ['height' => 220, 'label_every' => 12, 'color' => '#ffd23f']) ?></div>
  <div class="box"><h2>Historique mensuel des messages aux pros</h2><?= $monthly['messages'] ? Chart::bars(array_slice($monthly['messages'], -60, null, true), ['height' => 220, 'label_every' => 12, 'color' => '#8f7bff']) : '<p class="muted">—</p>' ?></div>
</div>
<div class="box mt-3"><h2>Appels à l'IA (30 derniers jours)</h2><?= Chart::bars(array_slice($ai, -30, null, true), ['height' => 160, 'color' => '#c8f560']) ?></div>
