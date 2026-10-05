<?php
/** Boutique : panier et commande. Variables : $lines, $sub, $ship, $config, $count, $flash, $old, $payable, $test */
use App\Shop\Orders;
use App\Vitrine\Host;

$o = fn (string $k, string $d = '') => (string) ($old[$k] ?? $d);
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => 'Votre panier', 'lead' => '', 'eyebrow' => 'Boutique', 'crumbs' => [['Boutique', Host::url('/boutique/')], ['Panier', Host::url('/boutique/panier/')]]]) ?>
<section class="section--tight">
  <div class="wrap">
    <?= \App\Core\View::partial('vitrine/partials/flash', ['flash' => $flash]) ?>
    <?php if (!$lines): ?>
      <p>Votre panier est vide. <a href="<?= e(Host::url('/boutique/')) ?>">Voir la boutique</a></p>
    <?php else: ?>
    <div class="shopcart">
      <?php foreach ($lines as $i => $l): ?>
      <div class="shopline">
        <div class="shopline__img"><?= $l['svg'] ?></div>
        <div class="shopline__txt">
          <b><?= e($l['name']) ?></b> <span class="muted">(<?= e($l['support']) ?>)</span><br>
          <small><?= e(Orders::describe($l)) ?></small>
        </div>
        <form method="post" action="<?= e(Host::url('/boutique/panier/')) ?>" class="shopline__qty">
          <?= csrf_field() ?><input type="hidden" name="do" value="qty"><input type="hidden" name="line" value="<?= $i ?>">
          <label class="sr-only" for="q<?= $i ?>">Quantité</label><input id="q<?= $i ?>" type="number" name="qty" min="1" max="20" value="<?= (int) $l['qty'] ?>" data-autosubmit>
          <noscript><button class="btn btn--ghost btn--sm">OK</button></noscript>
        </form>
        <b class="shopline__price"><?= e(Orders::money($l['total'])) ?></b>
        <form method="post" action="<?= e(Host::url('/boutique/panier/')) ?>">
          <?= csrf_field() ?><input type="hidden" name="do" value="remove"><input type="hidden" name="line" value="<?= $i ?>">
          <button class="shopline__del" aria-label="Retirer cet article">×</button>
        </form>
      </div>
      <?php endforeach; ?>
      <div class="shoptotal">
        <span>Sous-total</span><b><?= e(Orders::money($sub)) ?></b>
        <span>Livraison<?= $config['free_from'] > 0 && $ship > 0 ? ' <small>(offerte dès ' . e(Orders::money($config['free_from'])) . ')</small>' : '' ?></span><b><?= e($ship ? Orders::money($ship) : 'offerte') ?></b>
        <span class="shoptotal__all">Total</span><b class="shoptotal__all"><?= e(Orders::money($sub + $ship)) ?></b>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php if ($lines): ?>
<section class="section bg-sand bt" id="commander">
  <div class="wrap vform-wrap">
    <form class="vform" method="post" action="<?= e(Host::url('/boutique/commander/')) ?>" data-protect>
      <?= csrf_field() ?>
      <input type="hidden" name="_ts" value="<?= e(form_ts()) ?>">
      <div class="hp" aria-hidden="true"><label for="bo-w">Ne pas remplir</label><input type="text" id="bo-w" name="website" tabindex="-1" autocomplete="off"></div>
      <h2 class="h-2">Livraison et paiement</h2>
      <?php if ($test && $payable): ?><p class="alert">Mode essai : aucun paiement réel (carte de test 4242 4242 4242 4242).</p><?php endif; ?>
      <?php if (!$payable): ?><p class="alert alert--error">Le paiement en ligne n’est pas encore ouvert.</p><?php endif; ?>
      <div class="vgrid2">
        <div class="field"><label for="bo-name">Nom et prénom *</label><input type="text" id="bo-name" name="name" required maxlength="80" autocomplete="name" value="<?= e($o('name')) ?>"></div>
        <div class="field"><label for="bo-email">E-mail *</label><input type="email" id="bo-email" name="email" required maxlength="120" autocomplete="email" value="<?= e($o('email')) ?>"></div>
        <div class="field"><label for="bo-phone">Téléphone (pour le transporteur)</label><input type="tel" id="bo-phone" name="phone" maxlength="30" autocomplete="tel" value="<?= e($o('phone')) ?>"></div>
        <div class="field"><label for="bo-country">Pays *</label><select id="bo-country" name="country" autocomplete="country"><?php foreach (Orders::COUNTRIES as $k => $l): ?><option value="<?= $k ?>"<?= $o('country', 'FR') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label for="bo-l1">Adresse *</label><input type="text" id="bo-l1" name="line1" required maxlength="120" autocomplete="address-line1" value="<?= e($o('line1')) ?>"></div>
      <div class="field"><label for="bo-l2">Complément d’adresse</label><input type="text" id="bo-l2" name="line2" maxlength="120" autocomplete="address-line2" value="<?= e($o('line2')) ?>"></div>
      <div class="vgrid2">
        <div class="field"><label for="bo-zip">Code postal *</label><input type="text" id="bo-zip" name="zip" required maxlength="12" autocomplete="postal-code" value="<?= e($o('zip')) ?>"></div>
        <div class="field"><label for="bo-city">Ville *</label><input type="text" id="bo-city" name="city" required maxlength="80" autocomplete="address-level2" value="<?= e($o('city')) ?>"></div>
      </div>
      <?php if ($config['cgv'] !== ''): ?><details class="shopcgv"><summary>Conditions de vente</summary><div><?= nl2br(e($config['cgv'])) ?></div></details><?php endif; ?>
      <label class="checkbox"><input type="checkbox" name="cgv" value="1" required> <span>J’accepte les conditions de vente. Articles personnalisés et fabriqués pour moi : ni repris ni échangés, sauf défaut (<a href="<?= e(Host::url('/confidentialite/')) ?>">confidentialité</a>). *</span></label>
      <button type="submit" class="btn btn--navy btn--block"<?= $payable ? '' : ' disabled' ?>>Payer <?= e(Orders::money($sub + $ship)) ?> par carte</button>
      <p class="shophelp">Paiement sécurisé par Stripe. Vous recevez la confirmation et le lien de suivi par e-mail.</p>
    </form>
    <aside class="vform-side"><div class="vbox"><h2 class="h-3">Fabriqué près de chez nous</h2><p><?= e($config['delay']) ?></p><p>Une question sur votre commande ? Écrivez à l’imprimeur depuis la page de suivi.</p></div></aside>
  </div>
</section>
<?php endif; ?>
