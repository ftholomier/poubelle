<?php
/** Boutique › Relevés de l'imprimeur. Variables : $s (Accounts::statement()), $months (liste AAAA-MM) */
use App\Shop\Orders;

$m = $s['sums'];
$fr = fn (string $ym) => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'][(int) substr($ym, 5, 2) - 1] . ' ' . substr($ym, 0, 4);
?>
<link rel="stylesheet" href="<?= asset('admin/boutique.css') ?>">
<form method="get" action="/admin/boutique/releves" class="row" style="gap:10px;align-items:end;margin:0 0 14px;flex-wrap:wrap">
  <label class="f" style="margin:0"><span class="f__k">Mois</span><select class="in" name="mois"><?php foreach ($months as $ym): ?><option value="<?= e($ym) ?>"<?= $ym === $s['ym'] ? ' selected' : '' ?>><?= e($fr($ym)) ?></option><?php endforeach; ?></select></label>
  <button class="btn btn--ghost btn--sm">Afficher</button>
  <a class="btn btn--yellow btn--sm" href="/admin/boutique/releves/pdf?mois=<?= e($s['ym']) ?>">Relevé PDF</a>
  <a class="btn btn--ghost btn--sm" href="/admin/boutique/releves/csv?mois=<?= e($s['ym']) ?>">Tableur (CSV)</a>
</form>
<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= e(Orders::money($m['cost'] + $m['ship_cost'])) ?></b><span>à payer à l’imprimeur</span><small>fabrication <?= e(Orders::money($m['cost'])) ?> · expéditions <?= e(Orders::money($m['ship_cost'])) ?></small></div>
  <div class="kpi"><b><?= e(Orders::money($m['sales'])) ?></b><span>ventes encaissées</span><small><?= $m['orders'] ?> commandes · <?= $m['items'] ?> articles</small></div>
  <div class="kpi"><b><?= e(Orders::money($m['refunds'] + $m['fees'])) ?></b><span>remboursements et frais Stripe</span><small>remboursé <?= e(Orders::money($m['refunds'])) ?> · frais <?= e(Orders::money($m['fees'])) ?></small></div>
  <div class="kpi"><b><?= e(Orders::money($m['margin'])) ?></b><span>marge de l’association</span><small>ventes − remboursements − frais − fabrication et expédition</small></div>
</div>
<?php if (!$s['rows']): ?><p class="card card--pad muted">Aucune commande payée en <?= e($fr($s['ym'])) ?>.</p><?php else: ?>
<table class="shoporders card">
  <thead><tr><th>Date</th><th>Commande</th><th>Article</th><th>Qté</th><th>Coût unitaire</th><th>Coût</th></tr></thead>
  <tbody>
  <?php foreach ($s['rows'] as $r): ?>
    <tr><td><?= e(date('d/m', strtotime($r['date']))) ?></td><td><a href="/admin/boutique/commandes/<?= e($r['order']) ?>"><?= e($r['order']) ?></a> <span class="xs muted"><?= e($r['status']) ?></span></td><td><?= e($r['name']) ?><?= $r['support'] !== '' ? ' <span class="xs muted">(' . e($r['support']) . ($r['size'] !== '' ? ', ' . e($r['size']) : '') . ')</span>' : '' ?></td><td><?= (int) $r['qty'] ?></td><td><?= e(Orders::money($r['unit_cost'])) ?></td><td><?= e(Orders::money($r['cost'])) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<p class="xs muted" style="margin-top:10px">Coûts de fabrication : ceux des supports au moment de la commande (Boutique › Supports) ; coût d’expédition : Boutique › Réglages. L’imprimeur voit le même relevé dans son espace, sans les ventes ni la marge.</p>
