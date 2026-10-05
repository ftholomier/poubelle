<?php
/**
 * Boutique › Tableau de bord : ventes (jour, mois, année), courbe des 12 derniers mois, marge,
 * meilleures ventes, commandes à suivre, alertes, rapprochement avec Stripe.
 * Variables : $d (Accounts::dashboard()), $models, $supports, $rec (dernier rapprochement), $payable
 */
use App\Shop\Orders;

$m = fn (int $c) => Orders::money($c);
$max = max(1, max($d['months']));
$monthsFr = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
?>
<link rel="stylesheet" href="<?= asset('admin/boutique.css') ?>">
<?php if ($d['alerts']): ?>
<section class="card card--pad shopalerts">
  <h2 class="card__t card__t--sm">À suivre (<?= count($d['alerts']) ?>)</h2>
  <ul>
    <?php foreach ($d['alerts'] as $a): ?>
    <li class="is-<?= e($a['level']) ?>"><?php if ($a['order'] !== ''): ?><a href="/admin/boutique/commandes/<?= e($a['order']) ?>"><b><?= e($a['order']) ?></b></a> · <?php endif; ?><?= e($a['text']) ?></li>
    <?php endforeach; ?>
  </ul>
  <p class="xs muted" style="margin:6px 0 0">Les nouvelles alertes partent aussi chaque jour par e-mail à l’adresse d’alerte (Boutique › Réglages).</p>
</section>
<?php endif; ?>
<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= e($m($d['day']['sales'])) ?></b><span>aujourd’hui</span><small><?= $d['day']['orders'] ?> commande<?= $d['day']['orders'] > 1 ? 's' : '' ?></small></div>
  <div class="kpi"><b><?= e($m($d['month']['sales'])) ?></b><span>ce mois-ci</span><small><?= $d['month']['orders'] ?> commandes · panier moyen <?= e($m($d['month']['basket'])) ?></small></div>
  <div class="kpi"><b><?= e($m($d['year']['sales'])) ?></b><span>cette année</span><small><?= $d['year']['items'] ?> articles vendus</small></div>
  <a class="kpi" href="/admin/boutique/commandes?statut=paid"><b><?= $d['todo']['paid'] ?></b><span>à fabriquer</span><small><?= $d['todo']['production'] ?> en fabrication · <?= $d['todo']['shipped'] ?> expédiées</small></a>
</div>
<section class="card">
  <div class="card__head"><h2 class="card__t">Partage des ventes</h2><span class="card__note">commission de l’imprimeur réglée par support ou par modèle (taux en %)</span></div>
  <table class="xs" style="width:100%">
    <thead><tr><th style="text-align:left"></th><th>CA complet</th><th>Commission imprimeur</th><th>Remboursements et frais Stripe</th><th>Marge de l’association</th></tr></thead>
    <tbody>
    <?php foreach (['month' => 'Ce mois-ci', 'year' => 'Cette année', 'all' => 'Depuis l’ouverture'] as $pk => $pl): $p = $d[$pk]; ?>
      <tr><td><b><?= e($pl) ?></b></td><td style="text-align:right"><?= e($m($p['sales'])) ?></td><td style="text-align:right"><?= e($m($p['cost'] + $p['ship_cost'])) ?><br><span class="muted">fabrication <?= e($m($p['cost'])) ?> · envois <?= e($m($p['ship_cost'])) ?></span></td><td style="text-align:right">−<?= e($m($p['refunds'] + $p['fees'])) ?></td><td style="text-align:right"><b><?= e($m($p['margin'])) ?></b></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="xs muted" style="margin:6px 0 0">L’association encaisse les ventes (Stripe) et reverse chaque mois la commission à l’imprimeur (Boutique › Relevés) ; la marge lui reste acquise.</p>
</section>
<div class="cols" style="align-items:start">
  <section class="card card--pad">
    <h2 class="card__t">Ventes des 12 derniers mois</h2>
    <svg class="shopchart" viewBox="0 0 600 220" role="img" aria-label="Ventes par mois">
      <?php $i = 0; foreach ($d['months'] as $ym => $v): $h = round(170 * $v / $max); $x = 10 + $i * 49; ?>
        <rect x="<?= $x ?>" y="<?= 185 - $h ?>" width="36" height="<?= max(1, $h) ?>" fill="<?= $ym === date('Y-m') ? '#F6C400' : '#0E1F4D' ?>"><title><?= e($ym . ' : ' . $m($v)) ?></title></rect>
        <?php if ($v > 0): ?><text x="<?= $x + 18 ?>" y="<?= 180 - $h ?>" text-anchor="middle" font-size="10" fill="#0E1F4D"><?= e(number_format($v / 100, 0, ',', ' ')) ?></text><?php endif; ?>
        <text x="<?= $x + 18 ?>" y="203" text-anchor="middle" font-size="11" fill="#3A4A75"><?= e($monthsFr[(int) substr($ym, 5, 2) - 1]) ?></text>
      <?php $i++; endforeach; ?>
    </svg>
    <p class="xs muted" style="margin:4px 0 0">En euros, ventes encaissées (livraison comprise). Depuis l’ouverture : <?= e($m($d['all']['sales'])) ?>, <?= $d['all']['orders'] ?> commandes, marge <?= e($m($d['all']['margin'])) ?>.</p>
  </section>
  <section class="card card--pad">
    <h2 class="card__t">Meilleures ventes</h2>
    <?php if (!$d['top']): ?><p class="small muted">Pas encore de vente.</p><?php else: ?>
    <table class="shoporders"><thead><tr><th>Article</th><th>Vendus</th><th>Ventes</th></tr></thead><tbody>
      <?php foreach ($d['top'] as $t): ?><tr><td><?= e($t['name']) ?> <span class="xs muted">(<?= e($t['support']) ?>)</span></td><td><?= (int) $t['qty'] ?></td><td><?= e($m($t['sales'])) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </section>
</div>
<div class="cols" style="align-items:start">
  <section class="card card--pad">
    <h2 class="card__t">Stripe : rapprochement des paiements</h2>
    <p class="small" style="margin:0 0 8px">Compare les paiements reçus chez Stripe (30 derniers jours) aux commandes : une commande payée mais restée « en attente » (webhook perdu) est rattrapée ; les écarts de montant sont signalés. Les frais Stripe de chaque paiement sont relevés au passage.</p>
    <form method="post" action="/admin/boutique/rapprochement" data-busy="Rapprochement en cours…">
      <?= csrf_field() ?><button class="btn btn--navy btn--sm"<?= $payable ? '' : ' disabled title="Clés Stripe absentes"' ?>>Rapprocher avec Stripe</button>
    </form>
    <?php if ($rec): ?>
    <p class="xs muted" style="margin:10px 0 4px">Dernier rapprochement : <?= e(date('d/m/Y H:i', strtotime($rec['at']))) ?><?= $rec['fixed'] ? ' · ' . (int) $rec['fixed'] . ' commande(s) rattrapée(s)' : '' ?></p>
    <ul class="shopalerts__list">
      <?php foreach ($rec['rows'] as $r): if ($r['level'] === 'ok') { continue; } ?><li class="is-<?= e($r['level']) ?>"><a href="/admin/boutique/commandes/<?= e($r['order']) ?>"><?= e($r['order']) ?></a> · <?= e($r['text']) ?></li><?php endforeach; ?>
      <li class="is-ok"><?= count(array_filter($rec['rows'], fn ($r) => $r['level'] === 'ok')) ?> paiement(s) concordant(s).</li>
    </ul>
    <?php endif; ?>
  </section>
  <section class="card card--pad">
    <h2 class="card__t">Raccourcis</h2>
    <ul class="small" style="margin:8px 0 0;padding-left:18px">
      <li><a href="/admin/boutique/commandes">Commandes</a> · <a href="/admin/boutique/releves">Relevés de l’imprimeur</a> (PDF, tableur, marge)</li>
      <li><a href="/admin/boutique/modeles">Modèles</a> (<?= count(array_filter($models, fn ($x) => \App\Shop\Catalog::sellable($x))) ?> en vente) · <a href="/admin/boutique/textes">Banque de textes</a> · <a href="/admin/boutique/supports">Supports</a> (coûts de fabrication)</li>
      <li><a href="/admin/boutique/reglages">Réglages</a> · <a href="<?= e(url('/boutique/')) ?>" target="_blank">Voir la boutique ↗</a> · <a href="/imprimeur/" target="_blank">Espace imprimeur ↗</a></li>
    </ul>
  </section>
</div>
