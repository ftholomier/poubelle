<?php
/** Boutique › Commandes. Variables : $orders (filtrées), $all, $st */
use App\Shop\Orders;

$count = array_count_values(array_column($all, 'status'));
?>
<link rel="stylesheet" href="<?= asset('admin/boutique.css') ?>">
<div class="row" style="gap:6px;flex-wrap:wrap;margin:0 0 12px">
  <a class="btn btn--sm <?= $st === '' ? 'btn--navy' : 'btn--ghost' ?>" href="/admin/boutique/commandes">En cours</a>
  <?php foreach (Orders::STATUSES as $k => $label): ?>
  <a class="btn btn--sm <?= $st === $k ? 'btn--navy' : 'btn--ghost' ?>" href="/admin/boutique/commandes?statut=<?= e($k) ?>"><?= e($label) ?> (<?= (int) ($count[$k] ?? 0) ?>)</a>
  <?php endforeach; ?>
</div>
<?php if (!$orders): ?>
<p class="card card--pad muted">Aucune commande<?= $st !== '' ? ' à cette étape' : '' ?>.</p>
<?php else: ?>
<table class="tbl shoporders card">
  <thead><tr><th>Commande</th><th>Date</th><th>Client</th><th>Articles</th><th>Total</th><th>Étape</th></tr></thead>
  <tbody>
  <?php foreach ($orders as $o): ?>
    <tr>
      <td><a href="/admin/boutique/commandes/<?= e($o['id']) ?>"><b><?= e($o['id']) ?></b></a><?= $o['messages'] ? ' <span class="xs muted">💬 ' . count($o['messages']) . '</span>' : '' ?></td>
      <td><?= e(date('d/m/Y H:i', strtotime($o['created']))) ?></td>
      <td><?= e($o['customer']['name']) ?><br><span class="xs muted"><?= e($o['customer']['city']) ?></span></td>
      <td><?= array_sum(array_column($o['items'], 'qty')) ?></td>
      <td><?= e(Orders::money($o['total'])) ?><?= $o['refunded'] ? '<br><span class="xs muted">remboursé ' . e(Orders::money($o['refunded'])) . '</span>' : '' ?></td>
      <td><span class="shopst shopst--<?= e($o['status']) ?>"><?= e(Orders::STATUSES[$o['status']]) ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
