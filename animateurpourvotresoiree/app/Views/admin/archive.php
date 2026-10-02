<?php
use App\Core\Url;
/** @var string $table @var array $items @var array $columns @var string $q */
?>
<p><a class="link small" href="<?= e(Url::admin('archives')) ?>">← Archives</a></p>
<div class="adm-head"><div><h1><?= e($table) ?></h1><p><?= nf($total) ?> ligne(s)</p></div><a class="btn btn-sm" href="?<?= e(http_build_query(['q' => $q, 'format' => 'csv'])) ?>"><?= icon('download', 16) ?> CSV</a></div>
<form class="filters" method="get"><div class="field grow"><label for="aq">Rechercher</label><input id="aq" type="search" name="q" value="<?= e($q) ?>"></div><button class="btn btn-ink btn-sm" type="submit">Filtrer</button></form>
<div class="table-wrap"><table class="tbl"><thead><tr><?php foreach ($columns as $c): ?><th><?= e((string) $c) ?></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach ($items as $r): ?><tr><?php foreach ($columns as $c): $v = $r[$c] ?? ''; ?><td class="small"><span class="t-ex" style="max-width:280px"><?= e(is_scalar($v) ? (string) $v : json_encode($v)) ?></span></td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table></div>
<?= App\Core\View::partial('admin/partials/pager', ['page' => $page, 'pages' => $pages, 'link' => $link]) ?>
