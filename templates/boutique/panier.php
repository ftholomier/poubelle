<?php
/** Boutique : panier et commande. Variables : $lines, $sub, $promo, $discount, $ship, $config, $count, $flash, $old, $payable, $test */
use App\Shop\Orders;
use App\Shop\ShopPages;

$o = fn (string $k, string $d = '') => (string) ($old[$k] ?? $d);
?>
<?= \App\Core\View::partial('boutique/_head', ['title' => 'Votre panier', 'lead' => '', 'eyebrow' => 'Boutique', 'crumbs' => [['Boutique', ShopPages::u('/boutique/')], ['Panier', ShopPages::u('/boutique/panier/')]]]) ?>
<section class="section--tight">
  <div class="wrap">
    <ol class="shopflow" aria-label="Étapes de la commande"><li class="is-now"><span>1</span>Panier</li><li><span>2</span>Livraison</li><li><span>3</span>Paiement sécurisé</li></ol>
    <?= \App\Core\View::partial('vitrine/partials/flash', ['flash' => $flash]) ?>
    <?php if (!$lines): ?>
      <div class="shopempty">
        <p class="shopempty__t">Votre panier est vide.</p>
        <p>Des objets jaune et bleu, à votre nom ou à la date de votre match, fabriqués près de chez nous.</p>
        <a class="btn btn--navy" href="<?= e(ShopPages::u('/boutique/')) ?>">Découvrir la boutique</a>
      </div>
    <?php else: $total = $sub - $discount + $ship; $left = $config['free_from'] > 0 && $ship > 0 ? $config['free_from'] - ($sub - $discount) : 0; ?>
    <div class="shopbasket">
      <div class="shopbasket__items">
        <p class="shopbasket__count"><?= (int) $count ?> article<?= $count > 1 ? 's' : '' ?> · <a href="<?= e(ShopPages::u('/boutique/')) ?>">continuer mes achats</a></p>
        <?php foreach ($lines as $i => $l): ?>
        <article class="shopitemc">
          <div class="shopitemc__img"><?= $l['svg'] ?></div>
          <div class="shopitemc__body">
            <span class="eyebrow"><?= e($l['support']) ?></span>
            <h2 class="shopitemc__t"><?= e($l['name']) ?></h2>
            <ul class="shopchips"><?php foreach (array_filter(explode(' · ', Orders::describe($l))) as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
            <div class="shopitemc__foot">
              <form method="post" action="<?= e(ShopPages::u('/boutique/panier/')) ?>" class="shopstep" aria-label="Quantité">
                <?= csrf_field() ?><input type="hidden" name="do" value="qty"><input type="hidden" name="line" value="<?= $i ?>">
                <button name="qty" value="<?= max(1, (int) $l['qty'] - 1) ?>" aria-label="Un de moins"<?= $l['qty'] <= 1 ? ' disabled' : '' ?>>−</button>
                <span aria-live="polite"><?= (int) $l['qty'] ?></span>
                <button name="qty" value="<?= min(20, (int) $l['qty'] + 1) ?>" aria-label="Un de plus"<?= $l['qty'] >= 20 ? ' disabled' : '' ?>>+</button>
              </form>
              <form method="post" action="<?= e(ShopPages::u('/boutique/panier/')) ?>">
                <?= csrf_field() ?><input type="hidden" name="do" value="remove"><input type="hidden" name="line" value="<?= $i ?>">
                <button class="shoplink">Retirer</button>
              </form>
              <b class="shopitemc__price"><?= e(Orders::money($l['total'])) ?><?php if ($l['qty'] > 1): ?><small><?= e(Orders::money($l['unit'])) ?> l’unité</small><?php endif; ?></b>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <aside class="shopsum">
        <h2 class="shopsum__t">Récapitulatif</h2>
        <dl>
          <dt>Sous-total</dt><dd><?= e(Orders::money($sub)) ?></dd>
          <?php if ($promo): ?><dt>Code <?= e($promo['code']) ?> <small>(<?= e($promo['label']) ?>)</small></dt><dd class="shopsum__promo"><?= $discount ? '−' . e(Orders::money($discount)) : 'offert' ?></dd><?php endif; ?>
          <dt>Livraison</dt><dd><?= e($ship ? Orders::money($ship) : 'offerte') ?></dd>
        </dl>
        <?php if ($left > 0): $pct = max(4, min(100, (int) round(100 * ($sub - $discount) / $config['free_from']))); ?>
          <div class="shopship"><div class="shopship__bar"><span style="width:<?= $pct ?>%"></span></div><p>Plus que <b><?= e(Orders::money($left)) ?></b> pour la livraison offerte.</p></div>
        <?php elseif (!$ship): ?>
          <p class="shopship shopship--ok">✓ Livraison offerte</p>
        <?php endif; ?>
        <p class="shopsum__total"><span>Total TTC</span><b><?= e(Orders::money($total)) ?></b></p>
        <a class="btn btn--yellow btn--block shopsum__go" href="#commander">Passer commande</a>
        <?php if ($promo): ?>
          <form method="post" action="<?= e(ShopPages::u('/boutique/panier/')) ?>" class="shopsum__promoform"><?= csrf_field() ?><input type="hidden" name="do" value="unpromo"><button class="shoplink">Retirer le code <?= e($promo['code']) ?></button></form>
        <?php else: ?>
          <details class="shopsum__code"><summary>J’ai un code promo</summary>
            <form method="post" action="<?= e(ShopPages::u('/boutique/panier/')) ?>" class="shoppromo">
              <?= csrf_field() ?><input type="hidden" name="do" value="promo">
              <label class="sr-only" for="promo-code">Code promo</label><input type="text" id="promo-code" name="code" maxlength="30" autocomplete="off" placeholder="CODE"><button class="btn btn--navy btn--sm">OK</button>
            </form>
          </details>
        <?php endif; ?>
        <ul class="shopsum__trust"><li>🏭 <?= e($config['delay']) ?></li><li>🔒 Paiement sécurisé par carte (Stripe)</li><li>💛 Chaque achat soutient l’association</li></ul>
      </aside>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php if ($lines): ?>
<section class="section bg-sand bt" id="commander">
  <div class="wrap vform-wrap">
    <form class="vform" method="post" action="<?= e(ShopPages::u('/boutique/commander/')) ?>" data-protect>
      <?= csrf_field() ?>
      <input type="hidden" name="_ts" value="<?= e(form_ts()) ?>">
      <div class="hp" aria-hidden="true"><label for="bo-w">Ne pas remplir</label><input type="text" id="bo-w" name="website" tabindex="-1" autocomplete="off"></div>
      <h2 class="h-2"><span class="shopflow__n">2</span> Livraison et paiement</h2>
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
      <label class="checkbox"><input type="checkbox" name="cgv" value="1" required> <span>J’accepte les conditions de vente. Articles personnalisés et fabriqués pour moi : ni repris ni échangés, sauf défaut (<a href="<?= e(ShopPages::u('/confidentialite/')) ?>">confidentialité</a>). *</span></label>
      <button type="submit" class="btn btn--navy btn--block"<?= $payable ? '' : ' disabled' ?>>Payer <?= e(Orders::money($sub - $discount + $ship)) ?> par carte</button>
      <p class="shophelp">Paiement sécurisé par Stripe. Vous recevez la confirmation et le lien de suivi par e-mail.</p>
    </form>
    <aside class="vform-side"><div class="vbox"><h2 class="h-3">Fabriqué près de chez nous</h2><p><?= e($config['delay']) ?></p><p>Une question sur votre commande ? Écrivez à l’imprimeur depuis la page de suivi.</p></div></aside>
  </div>
</section>
<?php endif; ?>
