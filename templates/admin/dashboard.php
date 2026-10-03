<?php
/** Tableau de bord. Variables : voir Admin\Dashboard::index */
use App\Admin\Base;
use App\Front\Donations;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$max = max(1, max($series ?: [0]));
$pl = fn ($n, string $one, string $many): string => (int) $n > 1 ? $many : $one;
?>
<div class="kpis">
  <a class="kpi" href="/admin/matchs"><b><?= $fmt($counts['match']) ?></b><span><?= $pl($counts['match'], 'fiche match', 'fiches matchs') ?></span><small><?= $month ? '+' . $fmt($month) . ' ' . $pl($month, 'fiche', 'fiches') . ' ce mois' : 'aucune nouvelle ce mois' ?></small></a>
  <a class="kpi" href="/admin/personnes"><b><?= $fmt($counts['personne']) ?></b><span><?= $pl($counts['personne'], 'personne', 'personnes') ?></span><small><?= $fmt($noBirth) ?> sans lieu de naissance</small></a>
  <a class="kpi<?= $contribs ? ' kpi--yellow' : '' ?>" href="/admin/contributions"><b><?= $fmt($contribs) ?></b><span><?= $pl($contribs, 'contribution à valider', 'contributions à valider') ?></span><small><?= $oldest ? 'plus ancienne : ' . e(Base::ago(date('c', $oldest))) : 'file vide' ?></small></a>
  <a class="kpi<?= $high ? ' kpi--pink' : '' ?>" href="/admin/qualite"><b><?= $fmt($qualityTotal) ?></b><span><?= $pl($qualityTotal, 'alerte qualité', 'alertes qualité') ?></span><small><?= $fmt($high) ?> haute<?= $high > 1 ? 's' : '' ?></small></a>
  <a class="kpi" href="/admin/dons"><b><?= e(Donations::money($monthDons)) ?></b><span>dons ce mois</span><small><?= $fmt($gauge['raised']) ?> € au total</small></a>
  <a class="kpi" href="/admin/newsletter"><b><?= $fmt($subs) ?></b><span><?= $pl($subs, 'abonné newsletter', 'abonnés newsletter') ?></span><small>« Ce jour-là », chaque semaine</small></a>
</div>

<div class="cols">
  <div class="stack">
    <div class="card">
      <div class="card__head"><h2 class="card__t">À faire</h2><span class="card__note"><?= count($todos) ?> tâche<?= count($todos) > 1 ? 's' : '' ?></span></div>
      <?php foreach ($todos as [$c, $t, $a, $href]): ?>
        <a class="card__row" style="grid-template-columns:12px minmax(0,1fr) auto" href="<?= e($href) ?>"><span class="dot" style="background:<?= e($c) ?>;width:10px;height:10px"></span><span><?= e($t) ?></span><span class="d" style="font-weight:800;font-size:14px;text-transform:uppercase;color:var(--blue)"><?= e($a) ?> →</span></a>
      <?php endforeach; ?>
      <?php if (!$todos): ?><div class="card__body"><span class="ok">✓</span> Rien d’urgent. Bravo !</div><?php endif; ?>
    </div>
    <div class="card card--pad">
      <div class="row" style="justify-content:space-between;align-items:baseline"><h2 class="card__t">Audience · 30 jours</h2><a class="linkbtn" href="/admin/audience">Détails →</a></div>
      <div class="spark" aria-label="Pages vues par jour"><?php foreach ($series as $d => $n): ?><i style="height:<?= max(1, round($n / $max * 100)) ?>%" data-t="<?= e(date('d/m', strtotime($d))) ?> · <?= $fmt($n) ?>"></i><?php endforeach; ?></div>
      <span class="small muted"><?= $fmt(array_sum($series)) ?> pages vues · mesure interne, sans cookie<?= $top ? ' · les plus vues : ' . e(implode(', ', array_map(fn ($p) => $p === '/' ? 'accueil' : trim($p, '/'), array_slice(array_keys($top), 0, 3)))) : '' ?></span>
    </div>
    <?php if ($scheduled): ?>
      <div class="card">
        <div class="card__head"><h2 class="card__t">Publications programmées</h2></div>
        <?php foreach ($scheduled as $s): ?><a class="card__row" style="grid-template-columns:120px minmax(0,1fr)" href="/admin/fiche/<?= (int) $s['id'] ?>"><span class="d" style="font-weight:800"><?= e(date('d/m H:i', strtotime((string) $s['publish_at']))) ?></span><span><?= e($s['title']) ?></span></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="stack">
    <div class="card card--navy card--pad" style="gap:10px">
      <span class="d" style="font-weight:900;font-size:20px;text-transform:uppercase;color:var(--yellow)">Centenaire · J-<?= (int) $days ?></span>
      <div class="row" style="justify-content:space-between;font-size:15px"><span>100 moments publiés</span><b><?= (int) $momentsPub ?> / 100</b></div>
      <div class="bar"><i style="width:<?= (int) $momentsPub ?>%"></i></div>
      <div class="row" style="justify-content:space-between;font-size:15px"><span>Votes du Onze</span><b><?= $fmt($onze) ?></b></div>
      <div class="row" style="justify-content:space-between;font-size:15px"><span>Collecte</span><b><?= $fmt($gauge['raised']) ?> € / <?= $fmt($gauge['goal']) ?> €</b></div>
      <a class="btn btn--light btn--sm" href="/admin/moments" style="align-self:flex-start">Calendrier des 100 moments</a>
    </div>
    <div class="card">
      <div class="card__head"><h2 class="card__t">Activité récente</h2><a class="linkbtn" href="/admin/journal">Tout le journal →</a></div>
      <?php foreach ($activity as $a): ?>
        <div class="card__row" style="grid-template-columns:34px minmax(0,1fr)">
          <span class="avatar avatar--sm" style="background:<?= ($a['by'] ?? '') === 'Système' ? 'var(--sand)' : 'var(--yellow)' ?>"><?= e($a['initials'] ?? '?') ?></span>
          <span><b><?= e($a['by'] ?? '') ?></b> <?= e($a['action'] ?? '') ?> <?php if (!empty($a['title'])): ?><?= !empty($a['id']) ? '<a href="/admin/fiche/' . (int) $a['id'] . '">' . e($a['title']) . '</a>' : e($a['title']) ?><?php endif; ?><br><span class="xs muted"><?= e(Base::ago($a['at'] ?? null)) ?></span></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$activity): ?><div class="card__body muted">Aucune activité pour le moment.</div><?php endif; ?>
    </div>
  </div>
</div>
