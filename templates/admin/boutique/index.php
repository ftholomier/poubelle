<?php
/**
 * Boutique › Tableau de bord (lot A : modèles et supports ; commandes, paiements et imprimeur à venir).
 * Variables : $models, $supports
 */
$active = count(array_filter($models, fn ($m) => $m['active']));
?>
<p class="small" style="margin:0;max-width:95ch">La boutique ne vend que des objets <b>sans photo</b> : le logo de l’association et des textes (slogans, données du musée, prénom du client). Tout est vectoriel : le fichier envoyé à l’imprimeur est net à toutes les tailles.</p>
<div class="kpis">
  <a class="kpi kpi--yellow" href="/admin/boutique/modeles"><b><?= count($models) ?></b><span>modèles</span><small><?= $active ?> prêt<?= $active > 1 ? 's' : '' ?> à la vente</small></a>
  <a class="kpi" href="/admin/boutique/supports"><b><?= count(array_filter($supports, fn ($s) => $s['active'])) ?></b><span>supports actifs</span><small>t-shirt, mug, casquette, écharpe, poster…</small></a>
</div>
<div class="cols" style="align-items:start">
  <section class="card card--pad">
    <h2 class="card__t">Créer et vérifier</h2>
    <ul class="small" style="margin:8px 0 0;padding-left:18px">
      <li><a href="/admin/boutique/modeles">Modèles</a> : l’éditeur (logo, textes, formes, champs à remplir par le client), l’aperçu sur le produit et le PDF pour l’imprimeur.</li>
      <li><a href="/admin/boutique/supports">Supports</a> : les produits vierges de l’imprimeur (faces imprimables, fonds perdus, couleurs, tailles).</li>
    </ul>
  </section>
  <section class="card card--pad">
    <h2 class="card__t">À venir</h2>
    <p class="small muted" style="margin:8px 0 0">Produits et tarifs, boutique en ligne, paiement Stripe, espace de l’imprimeur et suivi des commandes (lot B) ; tableau de bord des ventes, remboursements, relevés de l’imprimeur, « Ton match » (lot C).</p>
  </section>
</div>
