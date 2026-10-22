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
  <?php if (\App\Core\Auth::isAdmin()): ?>  <a class="kpi" href="/admin/dons"><b><?= e(Donations::money($monthDons)) ?></b><span>dons ce mois</span><small><?= $fmt($gauge['raised']) ?> € au total</small></a><?php endif; ?>
  <a class="kpi" href="/admin/newsletter"><b><?= $fmt($subs) ?></b><span><?= $pl($subs, 'abonné newsletter', 'abonnés newsletter') ?></span><small>« Ce jour-là », chaque semaine</small></a>
</div>

<?php
// Validation des fiches par les historiens : une case par liste du back-office.
$vLabels = ['matchs' => ['Matchs', 'fiches de match'], 'personnes' => ['Personnes', 'fiches personnes'], 'articles' => ['Articles & pages', 'articles et pages'], 'objets' => ['Objets', 'objets des réserves']];
?>
<section class="vdash" aria-label="Validation des fiches">
  <h2 class="vbox__t">Validation des fiches</h2>
  <div class="vdash__grid">
    <?php foreach ($vLabels as $slug => [$label, $noun]): $v = $validation[$slug] ?? ['ok' => 0, 'todo' => 0]; $all = $v['ok'] + $v['todo']; $pct = \App\Services\Validation::pct($v['ok'], $all); $pctTodo = \App\Services\Validation::pct($v['todo'], $all); $width = $all ? round($v['ok'] * 100 / $all, 2) : 0; ?>
      <div class="vdash__card">
        <a class="vdash__name" href="/admin/<?= e($slug) ?>"><?= e($label) ?> →</a>
        <div class="vdash__pct"><b><?= e($pct) ?> %</b> validé<?= $v['ok'] > 1 ? 's' : '' ?></div>
        <div class="vbox__bar" role="img" aria-label="<?= e($pct) ?> % validés"><i style="width:<?= $width ?>%"></i></div>
        <div class="vdash__nums">
          <a class="vdash__ok" href="/admin/<?= e($slug) ?>?valid=validees"><b><?= $fmt($v['ok']) ?></b> ✔ validée<?= $v['ok'] > 1 ? 's' : '' ?> <em><?= e($pct) ?> %</em></a>
          <a class="vdash__todo" href="/admin/<?= e($slug) ?>?valid=a-valider"><b><?= $fmt($v['todo']) ?></b> à valider <em><?= e($pctTodo) ?> %</em></a>
        </div>
        <small><?= $fmt($all) ?> <?= e($noun) ?> au total</small>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<div class="cols">
  <div class="stack">
    <?php $me = \App\Core\Auth::user() ?? []; $mine = array_values(array_filter(\App\Services\Tickets::all(), fn ($t) => ($t['author']['id'] ?? '') === ($me['id'] ?? null) && in_array($t['status'], \App\Services\Tickets::OPEN, true))); ?>
    <div class="card">
      <div class="card__head"><h2 class="card__t">Mes tickets en cours</h2><a class="linkbtn" href="/admin/tickets">+ Nouveau ticket</a></div>
      <?php foreach ($mine as $t): $new = \App\Services\Tickets::unread($t, $me); ?>
        <a class="card__row" style="grid-template-columns:auto minmax(0,1fr) auto" href="/admin/tickets/<?= (int) $t['id'] ?>"><b style="color:var(--blue)">n° <?= (int) $t['id'] ?></b><span><?= $new ? '<span title="Du nouveau depuis votre dernière visite" style="display:inline-block;width:9px;height:9px;border-radius:50%;background:var(--yellow);box-shadow:0 0 0 2px var(--navy);margin-right:6px"></span>' : '' ?><?= e($t['title']) ?></span><span class="d" style="font-weight:800;font-size:13px;text-transform:uppercase;color:var(--blue)"><?= e(\App\Services\Tickets::STATES[$t['status']]) ?></span></a>
      <?php endforeach; ?>
      <?php if (!$mine): ?><div class="card__body">Aucun ticket en cours. Un bug, une idée&nbsp;? <a href="/admin/tickets">Ouvrir un ticket</a>.</div><?php endif; ?>
    </div>
    <div class="card">
      <div class="card__head"><h2 class="card__t">À faire</h2><span class="card__note"><?= count($todos) ?> tâche<?= count($todos) > 1 ? 's' : '' ?></span></div>
      <?php foreach ($todos as [$c, $t, $a, $href]): ?>
        <a class="card__row" style="grid-template-columns:12px minmax(0,1fr) auto" href="<?= e($href) ?>"><span class="dot" style="background:<?= e($c) ?>;width:10px;height:10px"></span><span><?= e($t) ?></span><span class="d" style="font-weight:800;font-size:14px;text-transform:uppercase;color:var(--blue)"><?= e($a) ?> →</span></a>
      <?php endforeach; ?>
      <?php if (!$todos): ?><div class="card__body"><span class="ok">✓</span> Rien d’urgent. Bravo !</div><?php endif; ?>
    </div>
    <div class="card card--pad">
      <div class="row" style="justify-content:space-between;align-items:baseline"><h2 class="card__t">Audience · 30 jours</h2><a class="linkbtn" href="/admin/audience">Détails →</a></div>
      <div class="spark" role="img" aria-label="Pages vues par jour"><?php foreach ($series as $d => $n): ?><i style="height:<?= max(1, round($n / $max * 100)) ?>%" data-t="<?= e(date('d/m', strtotime($d))) ?> · <?= $fmt($n) ?>"></i><?php endforeach; ?></div>
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
      <span class="d" style="font-weight:900;font-size:20px;text-transform:uppercase;color:var(--yellow)">Centenaire<?= \App\Front\Site::centenaryCountdown() ? ' · J-' . (int) $days : ' · ' . e(substr(\App\Front\Site::centenaryDate(), 0, 4)) ?></span>
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
