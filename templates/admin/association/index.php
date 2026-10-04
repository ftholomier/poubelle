<?php
/** Tableau de bord du site de l'association. Variables : $open, $base, $aliases, $checks, $kpi, $stats, $latest, $vol, $msgs, $year */
use App\Admin\Base;
use App\Vitrine\Host;
use App\Vitrine\Membership;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$high = count(array_filter($checks, fn ($c) => $c[0] === 'haute'));
$max = max(1, max($stats['days'] ?: [0]));
$host = (string) parse_url($base, PHP_URL_HOST);
?>
<div class="card card--pad<?= $open ? '' : ' card--navy' ?>">
  <div class="row" style="justify-content:space-between;gap:16px">
    <div class="stack" style="gap:6px;max-width:760px">
      <span class="d" style="font-weight:900;font-size:24px;text-transform:uppercase;<?= $open ? '' : 'color:var(--yellow)' ?>"><?= $open ? '● Site ouvert au public' : '○ Site fermé : page d’attente' ?></span>
      <span class="small"><?= $open
          ? 'Le site de l’association est visible sur <b>' . e($host) . '</b> et indexé par les moteurs de recherche (sauf réglage contraire).'
          : 'Les visiteurs de <b>' . e($host) . '</b> voient la page d’attente et rien n’est indexé. Vous pouvez tout préparer et vérifier dans l’aperçu.' ?>
        Le domaine doit mener au même dossier que le musée (cPanel › Domaines)<?= $aliases ? ' ; ' . e(implode(', ', $aliases)) . ' redirige vers ' . e($host) : '' ?>.</span>
    </div>
    <div class="row" style="gap:8px">
      <?php if (!$open): ?><a class="btn btn--light" href="/admin/association/attente">Page d’attente</a><?php endif; ?>
      <a class="btn<?= $open ? '' : ' btn--light' ?>" href="<?= e(Host::PREVIEW) ?>/" target="_blank" rel="noopener">Aperçu complet ↗</a>
      <?php if ($open): ?><a class="btn" href="<?= e($base) ?>/" target="_blank" rel="noopener"><?= e($host) ?> ↗</a><?php endif; ?>
      <form method="post" action="/admin/association/ouverture" data-confirm="<?= e($open ? 'Fermer le site au public ?|Les visiteurs verront la page d’attente et le site ne sera plus indexé.|Fermer le site|danger' : 'Ouvrir le site au public ?|' . ($high ? 'Il reste ' . $high . ' point(s) important(s) à vérifier (en rouge dans la liste). ' : '') . 'Le site deviendra visible de tous et indexé par les moteurs de recherche.|Ouvrir le site') ?>">
        <?= csrf_field() ?><input type="hidden" name="open" value="<?= $open ? '0' : '1' ?>">
        <button type="submit" class="btn <?= $open ? 'btn--ghost' : 'btn--yellow' ?>"><?= $open ? 'Fermer le site' : 'Ouvrir le site au public' ?></button>
      </form>
    </div>
  </div>
</div>

<div class="kpis">
  <a class="kpi" href="/admin/association/adhesions"><b><?= $fmt($kpi['members']) ?></b><span>adhérent<?= $kpi['members'] > 1 ? 's' : '' ?> <?= (int) $year ?></span><small><?= e(Membership::money($kpi['amount'])) ?> de cotisations</small></a>
  <a class="kpi<?= $kpi['waiting'] ? ' kpi--yellow' : '' ?>" href="/admin/association/adhesions?statut=offline"><b><?= $fmt($kpi['waiting']) ?></b><span>règlement<?= $kpi['waiting'] > 1 ? 's' : '' ?> attendu<?= $kpi['waiting'] > 1 ? 's' : '' ?></span><small>chèques ou espèces</small></a>
  <a class="kpi<?= $kpi['volunteers'] ? ' kpi--yellow' : '' ?>" href="/admin/association/benevoles"><b><?= $fmt($kpi['volunteers']) ?></b><span>proposition<?= $kpi['volunteers'] > 1 ? 's' : '' ?> de bénévolat</span><small>nouvelles</small></a>
  <a class="kpi<?= $kpi['messages'] ? ' kpi--yellow' : '' ?>" href="/admin/messages"><b><?= $fmt($kpi['messages']) ?></b><span>message<?= $kpi['messages'] > 1 ? 's' : '' ?> non lu<?= $kpi['messages'] > 1 ? 's' : '' ?></span><small>reçus par le site</small></a>
  <div class="kpi"><b><?= $fmt($kpi['views']) ?></b><span>pages vues</span><small>30 derniers jours, sans cookie</small></div>
</div>

<div class="cols">
  <div class="stack">
    <div class="card">
      <div class="card__head"><h2 class="card__t">À vérifier</h2><span class="card__note"><?= count($checks) ?> point<?= count($checks) > 1 ? 's' : '' ?></span></div>
      <?php foreach ($checks as [$sev, $text, $btn, $href]): ?>
        <a class="card__row" style="grid-template-columns:12px minmax(0,1fr) auto" href="<?= e($href) ?>"><span class="dot" style="background:<?= ['haute' => '#D9342B', 'moyenne' => '#F6C400', 'info' => '#C9CFE0'][$sev] ?>;width:10px;height:10px"></span><span><?= e($text) ?></span><span class="d" style="font-weight:800;font-size:14px;text-transform:uppercase;color:var(--blue)"><?= e($btn) ?> →</span></a>
      <?php endforeach; ?>
      <?php if (!$checks): ?><div class="card__body"><span class="ok">✓</span> Tout est en ordre.</div><?php endif; ?>
      <div class="card__body xs muted" style="border-top:1px solid var(--line, #ddd)">Les contenus livrés au départ (textes, actions, actualités, agenda d’exemple) sont rédigés à partir de ce que fait vraiment le musée ; ce qui ne pouvait pas être connu est marqué « à vérifier ». Aucun nom de personne, de partenaire ni article de presse n’a été inventé.</div>
    </div>
    <div class="card card--pad">
      <h2 class="card__t">Audience · 30 jours</h2>
      <div class="spark" role="img" aria-label="Pages vues par jour"><?php foreach ($stats['days'] as $d => $n): ?><i style="height:<?= max(1, round($n / $max * 100)) ?>%" data-t="<?= e(date('d/m', strtotime($d))) ?> · <?= $fmt($n) ?>"></i><?php endforeach; ?></div>
      <span class="small muted"><?= $fmt($stats['total']) ?> pages vues<?= $stats['top'] ? ' · les plus vues : ' . e(implode(', ', array_map(fn ($p) => $p === '/' ? 'accueil' : trim($p, '/'), array_slice(array_keys($stats['top']), 0, 4)))) : '' ?><?= $open ? '' : ' · comptées une fois le site ouvert' ?></span>
    </div>
  </div>
  <div class="stack">
    <div class="card">
      <div class="card__head"><h2 class="card__t">Dernières adhésions</h2><a class="linkbtn" href="/admin/association/adhesions">Toutes →</a></div>
      <?php foreach ($latest as $a): ?>
        <a class="card__row" style="grid-template-columns:minmax(0,1fr) auto" href="/admin/association/adhesions/<?= e($a['id']) ?>"><span><b><?= e(trim($a['member']['first'] . ' ' . $a['member']['last'])) ?></b> · <?= e($a['label']) ?><br><span class="xs muted"><?= e(Base::ago($a['created'])) ?> · <?= e(Membership::money((int) $a['amount'])) ?></span></span><span class="pill pill--<?= ['paid' => 'ok', 'offline' => 'warn', 'pending' => 'info'][$a['status']] ?? 'ko' ?>"><?= e(Membership::STATUS[$a['status']] ?? $a['status']) ?></span></a>
      <?php endforeach; ?>
      <?php if (!$latest): ?><div class="card__body muted">Aucune adhésion pour le moment.</div><?php endif; ?>
    </div>
    <div class="card">
      <div class="card__head"><h2 class="card__t">Bénévolat</h2><a class="linkbtn" href="/admin/association/benevoles">Toutes →</a></div>
      <?php foreach ($vol as $v): ?>
        <a class="card__row" style="grid-template-columns:minmax(0,1fr) auto" href="/admin/association/benevoles#<?= e($v['id']) ?>"><span><b><?= e(trim(($v['first'] ?? '') . ' ' . ($v['last'] ?? ''))) ?></b><br><span class="xs muted"><?= e(Base::ago($v['at'] ?? null)) ?><?= !empty($v['city']) ? ' · ' . e($v['city']) : '' ?></span></span><span class="pill pill--<?= ($v['status'] ?? 'nouveau') === 'nouveau' ? 'warn' : 'info' ?>"><?= e(\App\Vitrine\Forms::VOLUNTEER_STATUS[$v['status'] ?? 'nouveau'] ?? '') ?></span></a>
      <?php endforeach; ?>
      <?php if (!$vol): ?><div class="card__body muted">Aucune proposition pour le moment.</div><?php endif; ?>
    </div>
    <div class="card">
      <div class="card__head"><h2 class="card__t">Messages du site</h2><a class="linkbtn" href="/admin/messages">Boîte de réception →</a></div>
      <?php foreach ($msgs as $m): ?>
        <a class="card__row" style="grid-template-columns:minmax(0,1fr) auto" href="/admin/messages/<?= e($m['id']) ?>"><span><b><?= e($m['name'] ?? '') ?></b> · <?= e(\App\Vitrine\Forms::REASONS[$m['reason']] ?? $m['reason']) ?><br><span class="xs muted"><?= e(Base::ago($m['at'] ?? null)) ?></span></span><span class="pill pill--<?= ($m['status'] ?? 'nouveau') === 'nouveau' ? 'warn' : 'ok' ?>"><?= e(\App\Admin\Community::M_STATUS[$m['status'] ?? 'nouveau'] ?? '') ?></span></a>
      <?php endforeach; ?>
      <?php if (!$msgs): ?><div class="card__body muted">Aucun message reçu par le site.</div><?php endif; ?>
    </div>
  </div>
</div>
