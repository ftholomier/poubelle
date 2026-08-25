<?php
/** @var array $stats @var array $applications */
$stages = (array) settings('pipeline.stages', []);
$daily = $stats['daily'] ?? [];
$maxDaily = max(1, max(array_map(static fn ($d) => $d['views'], $daily ?: [['views' => 1]])));
$funnelMax = max(1, (int) ($stats['funnel'][0]['value'] ?? 1));
$totalStages = max(1, array_sum($by_stage));
?>
<div class="topbar">
  <h1>Tableau de bord</h1>
  <div class="row">
    <?php foreach ([7, 30, 90] as $d): ?>
      <a class="btn btn--sm <?= $days === $d ? '' : 'btn--ghost' ?>" href="<?= e(url('admin')) ?>?days=<?= $d ?>"><?= $d ?> jours</a>
    <?php endforeach; ?>
    <a class="btn btn--sm btn--ghost" href="<?= e(url('admin/candidatures/export')) ?>"><?= icon('download') ?> Export</a>
  </div>
</div>

<div class="grid grid--4">
  <div class="kpi">
    <div class="kpi__label">Candidatures reçues</div>
    <div class="kpi__value"><?= (int) $total_applications ?></div>
    <div class="kpi__sub"><?= (int) $week_applications ?> sur les 7 derniers jours</div>
  </div>
  <div class="kpi kpi--blue">
    <div class="kpi__label">Visiteurs uniques (<?= (int) $days ?> j)</div>
    <div class="kpi__value"><?= nb($stats['visitors']) ?></div>
    <div class="kpi__sub"><?= nb($stats['counts']['page_view']) ?> pages vues</div>
  </div>
  <div class="kpi kpi--mint">
    <div class="kpi__label">Taux de conversion</div>
    <div class="kpi__value"><?= e(number_format((float) $stats['conversion'], 2, ',', ' ')) ?> %</div>
    <div class="kpi__sub">visites → candidatures envoyées</div>
  </div>
  <div class="kpi kpi--amber">
    <div class="kpi__label">Tunnels abandonnés</div>
    <div class="kpi__value"><?= (int) $total_drafts ?></div>
    <div class="kpi__sub"><a href="<?= e(url('admin/candidatures')) ?>?drafts=1" style="color:#e8a13a">Relancer ces contacts →</a></div>
  </div>
</div>

<div class="grid grid--2" style="margin-top:18px">
  <div class="panel">
    <div class="panel__head"><h2>Entonnoir de conversion</h2><span class="badge"><?= (int) $days ?> derniers jours</span></div>
    <div class="funnelviz">
      <?php foreach ($stats['funnel'] as $f): $pct = $f['value'] / $funnelMax * 100; ?>
        <div class="funnelviz__row">
          <span><?= e($f['label']) ?></span>
          <span class="funnelviz__bar"><i style="width:<?= max(1.5, round($pct, 1)) ?>%"></i></span>
          <span class="funnelviz__val"><?= nb($f['value']) ?><br><span class="funnelviz__pct"><?= round($pct) ?> %</span></span>
        </div>
      <?php endforeach; ?>
    </div>
    <p style="margin-top:16px;font-size:.82rem;color:var(--muted)">
      Chaque étape franchie dans le formulaire est enregistrée : un abandon après l’étape « coordonnées » reste un contact exploitable.
    </p>
  </div>

  <div class="panel">
    <div class="panel__head"><h2>Trafic &amp; candidatures</h2></div>
    <?php
      $pts = [];
      $bars = [];
      $i = 0;
      $n = max(1, count($daily) - 1);
      foreach ($daily as $day => $d) {
          $x = ($i / $n) * 100;
          $y = 90 - ($d['views'] / $maxDaily) * 78;
          $pts[] = round($x, 2) . ',' . round($y, 2);
          if ($d['applications'] > 0) { $bars[] = [$x, $d['applications']]; }
          $i++;
      }
    ?>
    <svg class="spark" viewBox="0 0 100 96" preserveAspectRatio="none" role="img" aria-label="Évolution du trafic">
      <defs>
        <linearGradient id="sg" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#e62f43" stop-opacity=".38"/>
          <stop offset="100%" stop-color="#e62f43" stop-opacity="0"/>
        </linearGradient>
      </defs>
      <polygon fill="url(#sg)" points="0,96 <?= e(implode(' ', $pts)) ?> 100,96"/>
      <polyline fill="none" stroke="#e62f43" stroke-width="1.4" vector-effect="non-scaling-stroke" points="<?= e(implode(' ', $pts)) ?>"/>
      <?php foreach ($bars as [$x, $v]): ?>
        <circle cx="<?= round($x, 2) ?>" cy="<?= round(90 - ($v / max(1, $maxDaily)) * 78, 2) ?>" r="1.6" fill="#35d07f"/>
      <?php endforeach; ?>
    </svg>
    <div class="row" style="margin-top:10px;font-size:.8rem;color:var(--muted)">
      <span><?= e(fr_date(array_key_first($daily) ?: '')) ?></span>
      <span class="spacer"></span>
      <span>aujourd’hui</span>
    </div>

    <div style="margin-top:22px">
      <h3 style="font-size:.86rem;color:var(--muted);margin-bottom:12px">Répartition du pipeline</h3>
      <div class="stage-bar">
        <?php foreach ($stages as $s): $v = (int) ($by_stage[$s['key']] ?? 0); if (!$v) continue; ?>
          <span style="width:<?= round($v / $totalStages * 100, 2) ?>%;background:<?= e($s['color']) ?>"></span>
        <?php endforeach; ?>
      </div>
      <div class="stage-legend">
        <?php foreach ($stages as $s): ?>
          <span><i style="background:<?= e($s['color']) ?>"></i><?= e($s['label']) ?> · <?= (int) ($by_stage[$s['key']] ?? 0) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="panel" style="margin-top:18px">
  <div class="panel__head">
    <h2>Dernières candidatures</h2>
    <a class="btn btn--sm btn--ghost" href="<?= e(url('admin/candidatures')) ?>">Tout voir</a>
  </div>
  <?php if (!$applications): ?>
    <div class="empty"><b>Aucune candidature pour le moment</b>Les candidatures envoyées depuis le site apparaîtront ici.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Candidat</th><th>Secteur</th><th>Situation</th><th>Étape</th><th>Reçue le</th></tr></thead>
        <tbody>
          <?php foreach ($applications as $a): $st = null; foreach ($stages as $s) { if ($s['key'] === ($a['stage'] ?? '')) $st = $s; } ?>
            <tr>
              <td>
                <a class="strong" href="<?= e(url('admin/candidatures/' . $a['id'])) ?>"><?= e($a['name'] ?: 'Sans nom') ?></a>
                <div style="font-size:.8rem;color:var(--muted)"><?= e($a['email'] ?? '') ?></div>
              </td>
              <td><?= e($a['area'] ?? '—') ?></td>
              <td style="color:var(--muted)"><?= e($a['situation'] ?? '—') ?></td>
              <td><span class="badge" style="color:<?= e($st['color'] ?? '#8d99ae') ?>"><i></i><?= e($st['label'] ?? 'Nouveau') ?></span></td>
              <td style="color:var(--muted)"><?= e(fr_date($a['submitted_at'] ?? $a['created_at'] ?? '', true)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
