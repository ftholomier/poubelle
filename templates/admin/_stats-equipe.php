<?php
/**
 * Statistiques › Équipe connectée : tableau de bord des historiens. Variables : $t (StatsReport::team),
 * $live (Stats::live, mesure équipe), $label, $fmt.
 */
$people = $live['people'] ?? [];
$ago = fn (int $s) => $s < 60 ? 'à l’instant' : 'il y a ' . intdiv($s, 60) . ' min';
$tot = $t['bo'] + $t['site'];
$bar = function (array $list, bool $links = false) use ($fmt) {
    if (!$list) {
        return '<p class="muted small">Rien sur la période.</p>';
    }
    $m = max($list);
    $h = '<ol class="st-rank">';
    foreach ($list as $k => $c) {
        $name = $links ? '<a href="' . e((string) $k) . '" target="_blank" rel="noopener">' . e((string) $k) . '</a>' : e((string) $k);
        $h .= '<li><span class="st-rank__n">' . $name . '</span><b>' . $fmt($c) . '</b><i style="width:' . max(2, round($c / $m * 100)) . '%"></i></li>';
    }
    return $h . '</ol>';
};
$hmax = max(1, max($t['hours']));
$smax = max(1, max(array_map(fn ($x) => $x['bo'] + $x['site'], $t['series'] ?: [['bo' => 0, 'site' => 0]])));
?>
<style>
.te-live{display:grid;grid-template-columns:220px minmax(0,1fr);gap:22px;align-items:start;background:var(--navy);color:var(--cream);padding:18px 22px;border-left:6px solid var(--yellow)}
.te-live small{color:var(--mist)}
.te-who{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:8px}
.te-who li{background:rgba(255,255,255,.07);padding:8px 12px;border-left:3px solid var(--yellow)}
.te-who b{color:var(--yellow)}
.te-who span{display:block;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.te-tag{display:inline-block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;padding:1px 6px;margin-left:6px;background:var(--yellow);color:var(--navy)}
.te-tag--site{background:var(--cream)}
.te-tbl{width:100%;border-collapse:collapse}
.te-tbl th,.te-tbl td{text-align:left;padding:7px 8px;border-bottom:1px solid rgba(0,0,0,.08)}
.te-tbl td.n{text-align:right;font-family:var(--display);font-weight:800}
.te-days{display:flex;align-items:flex-end;gap:2px;height:150px}
.te-days div{flex:1;display:flex;flex-direction:column;justify-content:flex-end;height:100%}
.te-days i{display:block;background:var(--navy)}
.te-days i+i{background:var(--yellow)}
.te-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:16px}
.te-card h3{margin:0 0 6px;font-family:var(--display);text-transform:uppercase;font-size:20px}
.te-nums{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:13px;margin:0 0 10px;padding-bottom:8px;border-bottom:2px solid var(--yellow)}
.te-nums b{font-family:var(--display);font-size:16px}
.te-acts{list-style:none;margin:0;padding:0;font-size:13px}
.te-acts li{padding:4px 0;border-bottom:1px solid rgba(0,0,0,.06);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.te-acts time{font-weight:700;color:var(--blue);margin-right:4px}
@media (max-width:900px){.te-live{grid-template-columns:1fr}}
</style>

<section class="te-live" data-tlive>
  <div><div class="st-live__n"><i></i><span data-tlive-n><?= count($people) ?></span></div><small>historien<?= count($people) > 1 ? 's' : '' ?> connecté<?= count($people) > 1 ? 's' : '' ?> en ce moment (actifs dans les 5 dernières minutes), back-office et site</small></div>
  <ul class="te-who" data-tlive-who>
    <?php foreach ($people as $p): ?>
      <li><a href="/admin/journal?qui=<?= e(rawurlencode((string) $p['name'])) ?>" style="color:inherit"><b><?= e($p['name']) ?></b></a><span class="te-tag<?= $p['side'] === 'Site' ? ' te-tag--site' : '' ?>"><?= e($p['side']) ?></span><span><?= e($p['path']) ?> · <?= e($ago((int) $p['ago'])) ?></span></li>
    <?php endforeach; ?>
    <?php if (!$people): ?><li><span>Personne de l’équipe en ce moment.</span></li><?php endif; ?>
  </ul>
</section>

<p class="small muted" style="margin:14px 0 8px"><b><?= e($label) ?></b> · du <?= date('d/m/Y', strtotime($r['from'])) ?> au <?= date('d/m/Y', strtotime($r['to'])) ?></p>
<div class="kpis">
  <div class="kpi"><b><?= count(array_filter($t['people'], fn ($x) => $x['bo'] + $x['site'] + $x['edits'] > 0)) ?></b><span>historiens actifs</span></div>
  <div class="kpi"><b><?= $fmt($t['edits']) ?></b><span>actions enregistrées</span><small>créations, modifications, publications…</small></div>
  <div class="kpi"><b><?= $fmt($t['bo']) ?></b><span>pages du back-office</span></div>
  <div class="kpi"><b><?= $fmt($t['site']) ?></b><span>pages du site consultées</span><small>relecture côté public</small></div>
  <div class="kpi"><b><?= (int) $t['days_active'] ?></b><span>jours d’activité</span></div>
</div>

<h2 class="card__t card__t--sm" style="margin:22px 0 10px">L’équipe, historien par historien <small class="muted" style="font-size:14px">· cliquez un nom pour voir tout son journal</small></h2>
<div class="te-cards">
  <?php foreach ($t['people'] as $n => $x): ?>
  <article class="card card--pad te-card">
    <h3><a href="/admin/journal?qui=<?= e(rawurlencode((string) $n)) ?>"><?= e($n) ?></a></h3>
    <p class="te-nums"><span><b><?= $fmt($x['logins'] ?? 0) ?></b> connexion<?= ($x['logins'] ?? 0) > 1 ? 's' : '' ?> au BO</span><span><b><?= $fmt($x['edits']) ?></b> actions</span><span><b><?= $fmt($x['bo']) ?></b> pages BO</span><span><b><?= $fmt($x['site']) ?></b> pages site</span><span><b><?= (int) $x['days'] ?></b> j présents</span></p>
    <?php if ($x['recent']): ?>
    <ol class="te-acts"><?php foreach ($x['recent'] as $a): ?><li><time><?= e(date('d/m H:i', strtotime($a['at']))) ?></time> <?= e($a['action']) ?><?= $a['title'] !== '' ? ' <i>' . e(mb_strimwidth($a['title'], 0, 70, '…')) . '</i>' : '' ?></li><?php endforeach; ?></ol>
    <?php else: ?><p class="muted small">Aucune action enregistrée.</p><?php endif; ?>
  </article>
  <?php endforeach; ?>
  <?php if (!$t['people']): ?><p class="muted small">Pas encore de données.</p><?php endif; ?>
</div>

<div class="card card--pad" style="margin-top:16px"><h2 class="card__t card__t--sm">Activité jour par jour <small class="muted" style="font-size:14px"><span style="color:var(--navy)">■</span> back-office · <span style="color:#d9a800">■</span> site</small></h2>
  <div class="te-days"><?php foreach ($t['series'] as $d => $x): ?><div title="<?= e(date('d/m', strtotime($d))) ?> : <?= $x['bo'] ?> back-office, <?= $x['site'] ?> site"><i style="height:<?= round($x['site'] / $smax * 100) ?>%;background:var(--yellow)"></i><i style="height:<?= round($x['bo'] / $smax * 100) ?>%;background:var(--navy)"></i></div><?php endforeach; ?></div>
</div>

<div class="st-grid">
  <div class="card card--pad"><h2 class="card__t card__t--sm">Ce qui a été fait</h2><?= $bar($t['actions']) ?></div>
  <div class="card card--pad"><h2 class="card__t card__t--sm">Fiches les plus travaillées</h2><?= $bar($t['fiches']) ?></div>
  <div class="card card--pad"><h2 class="card__t card__t--sm">Rubriques du back-office les plus utilisées</h2><?= $bar($t['sections']) ?></div>
  <div class="card card--pad"><h2 class="card__t card__t--sm">Pages du site relues par l’équipe</h2><?= $bar($t['site_pages'], true) ?></div>
  <div class="card card--pad"><h2 class="card__t card__t--sm">Heures de travail</h2>
    <div class="st-mini" style="height:150px"><?php foreach ($t['hours'] as $h => $c): ?><i title="<?= $h ?> h : <?= $fmt($c) ?>" style="height:<?= max(2, round($c / $hmax * 100)) ?>%;background:var(--navy);border-top:3px solid var(--yellow)"></i><?php endforeach; ?></div>
    <div style="display:flex;justify-content:space-between" class="small muted"><span>0 h</span><span>6 h</span><span>12 h</span><span>18 h</span><span>23 h</span></div>
  </div>
</div>
<p class="small muted" style="margin-top:16px">Compté pour chaque membre connecté, à son nom : pages du back-office, pages du site vues en étant connecté, et actions du journal d’activité. Ces chiffres ne sont jamais mêlés à l’audience du public. Les pages vues avant cette mise à jour n’étaient pas rattachées à une personne (ni au back-office).</p>

<script nonce="<?= e(csp_nonce()) ?>">
(() => {
  const box = document.querySelector('[data-tlive]');
  if (!box) return;
  const esc = s => String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const ago = s => s < 60 ? 'à l’instant' : 'il y a ' + Math.floor(s / 60) + ' min';
  setInterval(async () => {
    if (document.hidden) return;
    try {
      const d = await (await fetch('/admin/statistiques/direct?m=equipe', {credentials: 'same-origin'})).json();
      const p = d.people || [];
      box.querySelector('[data-tlive-n]').textContent = p.length;
      box.querySelector('[data-tlive-who]').innerHTML = p.length ? p.map(x => '<li><a href="/admin/journal?qui=' + encodeURIComponent(x.name) + '" style="color:inherit"><b>' + esc(x.name) + '</b></a><span class="te-tag' + (x.side === 'Site' ? ' te-tag--site' : '') + '">' + esc(x.side) + '</span><span>' + esc(x.path) + ' · ' + ago(x.ago) + '</span></li>').join('') : '<li><span>Personne de l’équipe en ce moment.</span></li>';
    } catch (e) {}
  }, 15000);
})();
</script>
