<?php
/** Boutique › Réglages (administrateurs). Variables : $c (Orders::config()), $payable, $models */
use App\Shop\Catalog;
use App\Shop\Orders;

$sell = array_filter($models, fn ($m) => Catalog::sellable($m));
?>
<link rel="stylesheet" href="<?= asset('admin/boutique.css') ?>">
<form method="post" action="/admin/boutique/reglages" class="cols" style="align-items:start">
  <?= csrf_field() ?>
  <section class="card card--pad">
    <h2 class="card__t">Ouverture</h2>
    <label class="toggle"><input type="checkbox" name="open" value="1"<?= $c['open'] ? ' checked' : '' ?>><span class="toggle__box"></span><span><b>Boutique ouverte</b> sur le site du musée (adresse /boutique/, dans le menu ; le site de l’association y renvoie)</span></label>
    <ul class="small" style="margin:10px 0 0">
      <li><?= count($sell) ?> modèle<?= count($sell) > 1 ? 's' : '' ?> en vente (prêts à la vente, avec un prix).</li>
      <li>Paiement par carte (Stripe) : <?= $payable ? '<b>prêt</b>' : '<b style="color:var(--red)">clés Stripe absentes</b> : réglez-les dans Système › Réglages › Dons (les mêmes que pour les dons et les adhésions).' ?></li>
      <li>Avant l’ouverture, seule l’équipe connectée au back-office voit la boutique (pour les essais).</li>
    </ul>
    <h2 class="card__t" style="margin-top:18px">Livraison</h2>
    <div class="fgrid fgrid--2">
      <label class="f"><span class="f__k">Frais de port (€)</span><input class="in" type="number" step="0.01" min="0" name="shipping" value="<?= e(number_format($c['shipping'] / 100, 2, '.', '')) ?>"></label>
      <label class="f"><span class="f__k">Port offert dès (€, 0 : jamais)</span><input class="in" type="number" step="0.01" min="0" name="free_from" value="<?= e(number_format($c['free_from'] / 100, 2, '.', '')) ?>"></label>
    </div>
    <label class="f"><span class="f__k">Délai annoncé au client</span><input class="in" name="delay" value="<?= e($c['delay']) ?>"></label>
    <p class="xs muted">Envoi seulement (pas de retrait) : <?= e(implode(', ', Orders::COUNTRIES)) ?>.</p>
  </section>
  <section class="card card--pad">
    <h2 class="card__t">Imprimeur</h2>
    <p class="small" style="margin:0 0 8px">Il reçoit chaque commande payée, fabrique, expédie et répond aux clients depuis son espace : <a href="/imprimeur/" target="_blank">/imprimeur/</a> (connexion par un lien envoyé à son adresse).</p>
    <label class="f"><span class="f__k">Nom de l’imprimeur</span><input class="in" name="printer_name" value="<?= e($c['printer_name']) ?>"></label>
    <label class="f"><span class="f__k">Adresse e-mail de l’imprimeur</span><input class="in" type="email" name="printer_email" value="<?= e($c['printer_email']) ?>" autocomplete="off"></label>
    <p class="xs muted">Seule cette adresse peut ouvrir l’espace imprimeur. La changer coupe l’accès de l’ancienne adresse.</p>
    <h2 class="card__t" style="margin-top:18px">Alertes</h2>
    <label class="f"><span class="f__k">Prévenir l’association de chaque commande payée (e-mail)</span><input class="in" type="email" name="alert_email" value="<?= e($c['alert_email']) ?>"></label>
    <h2 class="card__t" style="margin-top:18px">Conditions de vente</h2>
    <label class="f"><span class="f__k">Texte affiché avant le paiement (délai, retours, contact…)</span><textarea class="in" name="cgv" rows="6"><?= e($c['cgv']) ?></textarea></label>
    <p class="xs muted">Produits personnalisés : le droit de rétractation ne s’applique pas (article L221-28 du Code de la consommation) ; un article défectueux est refait ou remboursé.</p>
    <div class="row" style="justify-content:flex-end"><button class="btn btn--navy">Enregistrer</button></div>
  </section>
</form>
