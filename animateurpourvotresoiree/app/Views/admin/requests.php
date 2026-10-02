<?php
use App\Core\Url;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Leads;

/** @var array $items @var array $counts @var array $filters */
$f = static fn (string $k) => (string) ($filters[$k] ?? '');
$tabUrl = static function (string $s) use ($filters): string { $q = $filters; unset($q['page']); $q['statut'] = $s; if ($s === '') { unset($q['statut']); } return '?' . http_build_query($q); };
?>
<div class="adm-head">
  <div><h1>Demandes <span class="serif">de devis</span></h1><p>Modération hybride : les demandes jugées sûres par l'anti-spam partent seules, les autres attendent ici.</p></div>
  <a class="btn btn-sm" href="<?= e(Url::admin('demandes/export.csv') . '?' . http_build_query($filters)) ?>"><?= icon('download', 16) ?> Exporter (CSV)</a>
</div>
<div class="tabs">
  <a href="<?= e($tabUrl('')) ?>"<?= $f('statut') === '' ? ' class="on"' : '' ?>>Toutes <em><?= nf($counts[''] ?? 0) ?></em></a>
  <?php foreach (Leads::REQUEST_STATUSES as $k => $l): ?><a href="<?= e($tabUrl($k)) ?>"<?= $f('statut') === $k ? ' class="on"' : '' ?>><?= e($l) ?> <em><?= nf($counts[$k] ?? 0) ?></em></a><?php endforeach; ?>
</div>
<form class="filters" method="get" data-autosubmit>
  <?php if ($f('statut') !== ''): ?><input type="hidden" name="statut" value="<?= e($f('statut')) ?>"><?php endif; ?>
  <?php if ($f('pro') !== ''): ?><input type="hidden" name="pro" value="<?= e($f('pro')) ?>"><?php endif; ?>
  <div class="field grow"><label for="rq">Recherche</label><input id="rq" type="search" name="q" value="<?= e($f('q')) ?>" placeholder="Nom, email, téléphone, ville, texte, n°…"></div>
  <div class="field"><label for="rdep">Département</label><select id="rdep" name="dep"><option value="">Tous</option><?php foreach (Geo::departements() as $code => $d): ?><option value="<?= e($code) ?>"<?= $f('dep') === (string) $code ? ' selected' : '' ?>><?= e($code . ' ' . $d['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="rcat">Métier</label><select id="rcat" name="cat"><option value="">Tous</option><?php foreach (Categories::all(true) as $s => $c): ?><option value="<?= e($s) ?>"<?= $f('cat') === $s ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="rsrc">Origine</label><select id="rsrc" name="source"><option value="">Toutes</option><?php foreach (['form' => 'Formulaire', 'favoris' => 'Favoris', 'assistant' => 'Assistant IA', 'legacy' => 'Ancien site'] as $k => $l): ?><option value="<?= e($k) ?>"<?= $f('source') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="rdu">Du</label><input id="rdu" type="date" name="du" value="<?= e($f('du')) ?>"></div>
  <div class="field"><label for="rau">Au</label><input id="rau" type="date" name="au" value="<?= e($f('au')) ?>"></div>
  <button class="btn btn-ink btn-sm" type="submit">Filtrer</button>
</form>
<?php if ($f('pro') !== ''): ?><p class="small">Filtré sur les demandes reçues par le pro <a href="<?= e(Url::admin('pros/' . (int) $f('pro'))) ?>">#<?= (int) $f('pro') ?></a> · <a href="?">retirer</a></p><?php endif; ?>
<div class="table-wrap">
  <table class="tbl">
    <thead><tr><th>Date</th><th>Client</th><th>Événement</th><th>Lieu</th><th>Demande</th><th>Statut</th><th class="num">Pros</th><th class="num">Spam</th></tr></thead>
    <tbody>
      <?php foreach ($items as $r): ?>
        <tr>
          <td class="small"><?= e(date_fr($r['created'], 'short')) ?><span class="t-sub"><?= e(date_fr($r['created'], 'time')) ?></span></td>
          <td><a class="t-main" href="<?= e(Url::admin('demandes/' . $r['id'])) ?>"><?= e($r['name'] ?: '—') ?></a><span class="t-sub"><?= e($r['email']) ?></span></td>
          <td class="small"><?= e(Leads::EVENT_TYPES[$r['type']] ?? '—') ?><?= $r['date'] ? '<span class="t-sub">' . e(date_fr($r['date'], 'short')) . '</span>' : '' ?></td>
          <td class="small"><?= e($r['city'] ?: '—') ?><?= $r['dep'] ? ' (' . e($r['dep']) . ')' : '' ?></td>
          <td><span class="t-ex"><?= e($r['excerpt']) ?></span></td>
          <td><span class="status-pill st-<?= e($r['status']) ?>"><?= e(Leads::REQUEST_STATUSES[$r['status']] ?? $r['status']) ?></span></td>
          <td class="num"><?= (int) $r['n'] ?></td>
          <td class="num"><span class="score <?= $r['score'] >= 70 ? 'hi' : ($r['score'] >= 30 ? 'mid' : 'lo') ?>"><?= (int) $r['score'] ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="8"><div class="empty-sm">Aucune demande.</div></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?= App\Core\View::partial('admin/partials/pager', ['page' => $page, 'pages' => $pages, 'link' => $link]) ?>
