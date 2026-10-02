<?php
use App\Core\Url;
/** @var array $items @var string $q */
?>
<div class="adm-head">
  <div><h1>Prospects</h1><p><?= nf($total) ?> adresses issues de l'ancien site (liste « liste »), dédoublonnées<?= $updated ? ' · importées ' . e(ago((string) $updated)) : '' ?>.</p></div>
  <a class="btn btn-sm" href="<?= e(Url::admin('prospects/export.csv')) ?>"><?= icon('download', 16) ?> Exporter (CSV)</a>
</div>
<div class="alert alert-info mb-2"><?= icon('info', 18) ?><div><strong>RGPD :</strong> ces adresses ont été collectées par l'ancien site. Avant toute prospection, assurez-vous de disposer d'une base légale (consentement ou intérêt légitime pour des professionnels) et proposez toujours un lien de désinscription.</div></div>
<form class="filters" method="get"><div class="field grow"><label for="pq">Rechercher</label><input id="pq" type="search" name="q" value="<?= e($q) ?>"></div><button class="btn btn-ink btn-sm" type="submit">Filtrer</button></form>
<div class="table-wrap"><table class="tbl"><thead><tr><th>Email</th><th>Source</th><th>Désinscrit</th></tr></thead><tbody>
  <?php foreach ($items as $p): ?><tr><td><?= e((string) $p['email']) ?></td><td class="small"><?= e((string) ($p['source'] ?? '')) ?></td><td class="small"><?= !empty($p['unsubscribed_at']) ? e(date_fr((string) $p['unsubscribed_at'], 'short')) : '—' ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?= App\Core\View::partial('admin/partials/pager', ['page' => $page, 'pages' => $pages, 'link' => $link]) ?>
