<?php
/** Pilotage › Statistiques. Variables : $r (StatsReport::build), $label, $preset, $live, $resetAt */
use App\Services\Stats;
use App\Services\StatsReport;

$fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
$k = $r['kpi'];
$p = $r['prev'];
$q = fn (array $x) => '?' . http_build_query($x);
$cur = $preset !== '' ? ['p' => $preset] : ['du' => $r['from'], 'au' => $r['to']];
$delta = function (string $key, bool $lowerIsBetter = false) use ($k, $p) {
    $d = StatsReport::delta($k[$key], $p[$key]);
    if ($d === null) {
        return '<small class="st-d">—</small>';
    }
    $good = $lowerIsBetter ? $d <= 0 : $d >= 0;
    return '<small class="st-d ' . ($good ? 'is-up' : 'is-down') . '">' . ($d > 0 ? '▲ +' : ($d < 0 ? '▼ ' : '= ')) . $d . ' %</small>';
};
// Courbe : pages vues (aire) et visiteurs (trait).
$ser = $r['series'];
$n = max(1, count($ser));
$mx = max(1, max(array_map(fn ($x) => $x['views'], $ser ?: [['views' => 0]])));
$W = 900; $H = 230; $pad = 34;
$xy = function (int $i, int $v) use ($n, $mx, $W, $H, $pad) {
    $x = $pad + ($n > 1 ? $i / ($n - 1) : .5) * ($W - 2 * $pad);
    $y = $H - 26 - $v / $mx * ($H - 50);
    return round($x, 1) . ',' . round($y, 1);
};
$pv = []; $pu = []; $i = 0;
foreach ($ser as $d => $x) { $pv[] = $xy($i, $x['views']); $pu[] = $xy($i, $x['visitors']); $i++; }
$hmax = max(1, max($r['hours']));
$heatMax = max(1, max(array_map('max', $r['heat'])));
$devTotal = max(1, array_sum($r['dev']));
$bar = function (array $list, string $kind = 'path') use ($r, $fmt) {
    if (!$list) {
        return '<p class="muted small">Pas encore de données sur la période.</p>';
    }
    $m = max($list);
    $h = '<ol class="st-rank">';
    foreach ($list as $key => $c) {
        $name = $kind === 'path' ? StatsReport::label($r, (string) $key) : (string) $key;
        $link = $kind === 'path' ? '<a href="' . e((string) $key) . '" target="_blank" rel="noopener">' . e($name) . '</a>' : e($name);
        $h .= '<li><span class="st-rank__n">' . $link . '</span><b>' . $fmt($c) . '</b><i style="width:' . max(2, round($c / $m * 100)) . '%"></i></li>';
    }
    return $h . '</ol>';
};
$tips = StatsReport::tips($r);
?>
<style>
.st-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:16px}
.st-bar a{padding:6px 12px;border:2px solid var(--navy);font-family:var(--display);font-weight:800;font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--navy)}
.st-bar a.is-on{background:var(--navy);color:var(--yellow)}
.st-bar form{display:flex;gap:6px;align-items:center;margin-left:auto}
.st-bar input[type=date]{padding:5px 8px;border:2px solid var(--navy)}
.st-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;margin-top:16px}
.st-live{display:grid;grid-template-columns:220px minmax(0,1fr) minmax(0,1fr);gap:18px;align-items:center;background:var(--navy);color:var(--cream);padding:18px 22px;border-left:6px solid var(--yellow)}
.st-live__n{font-family:var(--display);font-weight:900;font-size:64px;line-height:1;color:var(--yellow);display:flex;align-items:center;gap:12px}
.st-live__n i{width:14px;height:14px;border-radius:50%;background:#35d07f;box-shadow:0 0 0 0 rgba(53,208,127,.7);animation:stPulse 1.6s infinite}
@keyframes stPulse{70%{box-shadow:0 0 0 14px rgba(53,208,127,0)}100%{box-shadow:0 0 0 0 rgba(53,208,127,0)}}
.st-live small{color:var(--mist)}
.st-live ol{margin:0;padding-left:18px;font-size:14px;max-height:120px;overflow:hidden}
.st-live a{color:var(--cream)}
.st-mini{display:flex;align-items:flex-end;gap:2px;height:70px}
.st-mini i{flex:1;background:var(--yellow);min-height:2px;opacity:.85}
.st-d{font-weight:800}.st-d.is-up{color:#1f8f4e}.st-d.is-down{color:#c0392b}
.st-rank{list-style:none;margin:0;padding:0;counter-reset:r}
.st-rank li{position:relative;display:flex;gap:10px;align-items:baseline;padding:6px 8px 6px 34px;counter-increment:r;border-bottom:1px solid rgba(14,31,77,.08)}
.st-rank li::before{content:counter(r);position:absolute;left:6px;font-family:var(--display);font-weight:900;color:var(--blue)}
.st-rank__n{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;position:relative;z-index:1}
.st-rank b{position:relative;z-index:1;font-family:var(--display)}
.st-rank i{position:absolute;left:0;bottom:0;height:3px;background:var(--yellow)}
.st-heat{display:grid;grid-template-columns:40px repeat(24,1fr);gap:2px;font-size:11px}
.st-heat span{text-align:center;color:var(--muted)}
.st-heat b{height:18px;background:var(--blue);display:block}
.st-tips{background:var(--yellow);padding:16px 20px;border:2px solid var(--navy)}
.st-tips h2{margin:0 0 8px;font-family:var(--display);text-transform:uppercase}
.st-tips li{margin:4px 0}
.st-dec{display:flex;align-items:flex-end;gap:8px;height:170px;padding-top:10px}
.st-dec div{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;gap:4px;font-size:12px}
.st-dec i{width:100%;background:var(--navy);display:block;border-top:4px solid var(--yellow)}
@media (max-width:900px){.st-live{grid-template-columns:1fr}.st-bar form{margin-left:0}}
</style>

<div class="st-bar">
  <?php foreach (StatsReport::PRESETS as $key => $lab): ?>
    <a href="<?= e($q(['p' => $key])) ?>"<?= $preset === $key ? ' class="is-on"' : '' ?>><?= e($lab) ?></a>
  <?php endforeach; ?>
  <form method="get" action="/admin/statistiques">
    <input type="date" name="du" value="<?= e($r['from']) ?>" max="<?= date('Y-m-d') ?>" aria-label="Du">
    <input type="date" name="au" value="<?= e($r['to']) ?>" max="<?= date('Y-m-d') ?>" aria-label="Au">
    <button class="btn btn--sm">Filtrer</button>
    <a class="btn btn--sm btn--yellow" href="/admin/statistiques/rapport.pdf<?= e($q($cur)) ?>">Rapport PDF</a>
  </form>
</div>

<section class="st-live" data-live>
  <div><div class="st-live__n"><i></i><span data-live-online><?= (int) $live['online'] ?></span></div><small>visiteur<?= $live['online'] > 1 ? 's' : '' ?> en ligne maintenant (5 dernières minutes) · <span data-live-today><?= $fmt($live['today']) ?></span> pages vues aujourd’hui</small></div>
  <div><small>Pages vues, minute par minute (30 min)</small><div class="st-mini" data-live-minutes><?php $mm = max(1, max($live['minutes'])); foreach ($live['minutes'] as $c): ?><i style="height:<?= max(3, round($c / $mm * 100)) ?>%"></i><?php endforeach; ?></div></div>
  <div><small>Ce qu’ils regardent</small><ol data-live-pages><?php foreach ($live['pages'] as $pth => $c): ?><li><a href="<?= e($pth) ?>" target="_blank" rel="noopener"><?= e($pth) ?></a> (<?= (int) $c ?>)</li><?php endforeach; ?><?php if (!$live['pages']): ?><li>Personne pour l’instant</li><?php endif; ?></ol></div>
</section>

<p class="small muted" style="margin:14px 0 8px"><b><?= e($label) ?></b> · du <?= date('d/m/Y', strtotime($r['from'])) ?> au <?= date('d/m/Y', strtotime($r['to'])) ?>, comparé aux <?= (int) $r['len'] ?> jours précédents<?= $resetAt ? ' · compteurs remis à zéro le ' . date('d/m/Y', strtotime($resetAt)) : '' ?></p>
<div class="kpis">
  <div class="kpi"><b><?= $fmt($k['visitors']) ?></b><span>visiteurs</span><?= $delta('visitors') ?></div>
  <div class="kpi"><b><?= $fmt($k['visits']) ?></b><span>visites</span><?= $delta('visits') ?></div>
  <div class="kpi"><b><?= $fmt($k['views']) ?></b><span>pages vues</span><?= $delta('views') ?></div>
  <div class="kpi"><b><?= str_replace('.', ',', (string) $k['ppv']) ?></b><span>pages par visite</span><?= $delta('ppv') ?></div>
  <div class="kpi"><b><?= $k['bounce'] ?> %</b><span>visites d’une seule page</span><?= $delta('bounce', true) ?></div>
  <div class="kpi"><b><?= $k['en_pct'] ?> %</b><span>en anglais</span><small>version /en/</small></div>
</div>

<?php if ($tips): ?>
<section class="st-tips" style="margin-top:16px"><h2>À retenir</h2><ul><?php foreach ($tips as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul></section>
<?php endif; ?>

<div class="card card--pad" style="margin-top:16px">
  <h2 class="card__t">Fréquentation <?= $r['step'] > 1 ? 'semaine par semaine' : 'jour par jour' ?> <small class="muted" style="font-size:14px">■ pages vues · <span style="color:var(--blue)">— visiteurs</span></small></h2>
  <svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" role="img" aria-label="Courbe de fréquentation">
    <?php for ($g = 0; $g <= 4; $g++): $gy = $H - 26 - $g / 4 * ($H - 50); ?><line x1="<?= $pad ?>" x2="<?= $W - $pad ?>" y1="<?= $gy ?>" y2="<?= $gy ?>" stroke="#e3dccb"/><text x="2" y="<?= $gy + 4 ?>" font-size="11" fill="#6b7398"><?= $fmt($mx * $g / 4) ?></text><?php endfor; ?>
    <?php if (count($pv) > 1): ?>
    <polygon points="<?= $pad ?>,<?= $H - 26 ?> <?= implode(' ', $pv) ?> <?= $W - $pad ?>,<?= $H - 26 ?>" fill="rgba(246,196,0,.45)" stroke="none"/>
    <polyline points="<?= implode(' ', $pv) ?>" fill="none" stroke="#d9a800" stroke-width="2"/>
    <polyline points="<?= implode(' ', $pu) ?>" fill="none" stroke="#1E3FA8" stroke-width="3"/>
    <?php endif; ?>
    <?php $i = 0; $every = max(1, (int) ceil($n / 10)); foreach ($ser as $d => $x): if ($i % $every === 0): [$tx] = explode(',', $xy($i, 0)); ?><text x="<?= $tx ?>" y="<?= $H - 6 ?>" font-size="11" text-anchor="middle" fill="#6b7398"><?= date('d/m', strtotime($d)) ?></text><?php endif; $i++; endforeach; ?>
  </svg>
</div>

<div class="st-grid">
  <div class="card card--pad"><h2 class="card__t card__t--sm">Heures de visite</h2>
    <div class="st-mini" style="height:150px"><?php foreach ($r['hours'] as $h => $c): ?><i title="<?= $h ?> h : <?= $fmt($c) ?>" style="height:<?= max(2, round($c / $hmax * 100)) ?>%;background:var(--navy);border-top:3px solid var(--yellow)"></i><?php endforeach; ?></div>
    <div style="display:flex;justify-content:space-between" class="small muted"><span>0 h</span><span>6 h</span><span>12 h</span><span>18 h</span><span>23 h</span></div>
  </div>
  <div class="card card--pad"><h2 class="card__t card__t--sm">Jour × heure</h2>
    <div class="st-heat"><span></span><?php for ($h = 0; $h < 24; $h++): ?><span><?= $h % 6 ? '' : $h ?></span><?php endfor; ?>
      <?php foreach ($r['heat'] as $wd => $row): ?><span><?= StatsReport::WEEKDAYS[$wd] ?></span><?php foreach ($row as $h => $c): ?><b title="<?= StatsReport::WEEKDAYS[$wd] ?> <?= $h ?> h : <?= $fmt($c) ?>" style="opacity:<?= $c ? round(.12 + .88 * $c / $heatMax, 2) : .05 ?>"></b><?php endforeach; ?><?php endforeach; ?>
    </div>
  </div>
  <div class="card card--pad"><h2 class="card__t card__t--sm">Appareils</h2>
    <?php $acc = 0; $cols = ['mobile' => '#F6C400', 'ordinateur' => '#0E1F4D', 'tablette' => '#1E3FA8']; ?>
    <div style="display:flex;gap:18px;align-items:center">
      <svg viewBox="0 0 42 42" width="150" height="150"><circle cx="21" cy="21" r="15.9" fill="none" stroke="#eee6d2" stroke-width="7"/>
        <?php foreach ($r['dev'] as $dv => $c): $pct = $c / $devTotal * 100; ?><circle cx="21" cy="21" r="15.9" fill="none" stroke="<?= $cols[$dv] ?? '#999' ?>" stroke-width="7" stroke-dasharray="<?= round($pct, 2) ?> <?= round(100 - $pct, 2) ?>" stroke-dashoffset="<?= round(25 - $acc, 2) ?>"/><?php $acc += $pct; endforeach; ?></svg>
      <ul style="list-style:none;padding:0;margin:0"><?php foreach ($r['dev'] as $dv => $c): ?><li><b style="color:<?= $cols[$dv] ?? '#999' ?>">■</b> <?= e(Stats::DEVICES[$dv] ?? $dv) ?> · <b><?= round($c / $devTotal * 100) ?> %</b></li><?php endforeach; ?><?php if (!$r['dev']): ?><li class="muted">Pas encore de données</li><?php endif; ?></ul>
    </div>
  </div>
  <div class="card card--pad"><h2 class="card__t card__t--sm">D’où viennent les visiteurs</h2><?= $bar($r['ref'], 'text') ?></div>
</div>

<div class="card card--pad" style="margin-top:16px"><h2 class="card__t card__t--sm">Décennies les plus consultées</h2>
  <?php $dm = max(1, max($r['decades'] ?: [0])); ?>
  <div class="st-dec"><?php for ($dc = 1920; $dc <= (int) (intdiv((int) date('Y'), 10) * 10); $dc += 10): $c = $r['decades'][(string) $dc] ?? 0; ?><div><b><?= $c ? $fmt($c) : '' ?></b><i style="height:<?= max(2, round($c / $dm * 100)) ?>%"></i><span>’<?= substr((string) $dc, 2) ?></span></div><?php endfor; ?></div>
</div>

<div class="st-grid">
  <div class="card card--pad"><h2 class="card__t card__t--sm">Top 10 joueurs</h2><?= $bar($r['players']) ?></div>
  <div class="card card--pad"><h2 class="card__t card__t--sm">Top 10 matchs</h2><?= $bar($r['matches']) ?></div>
  <div class="card card--pad"><h2 class="card__t card__t--sm">Top 10 récits et articles</h2><?= $bar($r['stories']) ?></div>
  <div class="card card--pad"><h2 class="card__t card__t--sm">Top 10 rubriques du musée</h2><?= $bar($r['sections'], 'text') ?></div>
  <div class="card card--pad"><h2 class="card__t card__t--sm">Top 15 pages</h2><?= $bar($r['top']) ?></div>
  <div class="card card--pad"><h2 class="card__t card__t--sm">Jeux et expériences (Interactif)</h2><?= $bar($r['games'], 'text') ?></div>
  <div class="card card--pad"><h2 class="card__t card__t--sm">Ce que les visiteurs cherchent</h2><?= $bar($r['searches'], 'text') ?></div>
</div>

<p class="small muted" style="margin-top:16px">Mesure interne sans cookie et sans adresse IP conservée (exemptée de consentement) : un visiteur est reconnu par une empreinte anonyme qui change chaque jour, un même visiteur revenu un autre jour compte donc à nouveau. Une visite s’arrête après 30 minutes sans page vue. Les robots d’indexation et l’équipe tant que le site est fermé ne sont pas comptés.</p>

<section class="card card--pad" style="margin-top:20px;border-color:#c0392b">
  <h2 class="card__t card__t--sm">Remise à zéro avant l’ouverture</h2>
  <p class="small">Repart de zéro : tous les compteurs, courbes et classements sont vidés. Les anciennes données ne sont pas effacées, elles sont mises de côté sur le serveur. Pour confirmer, tapez <b>REMETTRE A ZERO</b>.</p>
  <form method="post" action="/admin/statistiques/remise-a-zero" class="row" data-confirm="Remettre les statistiques à zéro ?|Tous les compteurs repartent de zéro (les anciennes données sont gardées de côté).|Remettre à zéro|danger">
    <?= csrf_field() ?><input class="in in--sm" name="confirm" placeholder="REMETTRE A ZERO" autocomplete="off" required>
    <button class="btn btn--sm btn--danger">Remettre à zéro</button>
  </form>
</section>

<script nonce="<?= e(csp_nonce()) ?>">
(() => {
  const box = document.querySelector('[data-live]');
  if (!box) return;
  const esc = s => s.replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const tick = async () => {
    try {
      const d = await (await fetch('/admin/statistiques/direct', {credentials: 'same-origin'})).json();
      box.querySelector('[data-live-online]').textContent = d.online;
      box.querySelector('[data-live-today]').textContent = d.today.toLocaleString('fr-FR');
      const m = Math.max(1, ...d.minutes);
      box.querySelector('[data-live-minutes]').innerHTML = d.minutes.map(c => '<i style="height:' + Math.max(3, Math.round(c / m * 100)) + '%"></i>').join('');
      const pages = Object.entries(d.pages);
      box.querySelector('[data-live-pages]').innerHTML = pages.length ? pages.map(([p, c]) => '<li><a href="' + esc(p) + '" target="_blank" rel="noopener">' + esc(p) + '</a> (' + c + ')</li>').join('') : '<li>Personne pour l’instant</li>';
    } catch (e) {}
  };
  setInterval(() => document.hidden || tick(), 15000);
})();
</script>
