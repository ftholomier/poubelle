<?php
/** Boutique : suivi d'une commande (lien secret envoyé au client). Variables : $o, $previews, $flash, $config, $count */
use App\Shop\Orders;
use App\Vitrine\Host;

$steps = array_keys(Orders::STEPS);
$cur = array_search($o['status'], $steps, true);
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => 'Commande ' . $o['id'], 'lead' => $o['status'] === 'pending' ? 'En attente du paiement.' : 'Merci ' . $o['customer']['name'] . ' ! Suivez ici la fabrication et l’envoi de votre commande.', 'eyebrow' => 'Boutique', 'crumbs' => [['Boutique', Host::url('/boutique/')]]]) ?>
<section class="section--tight">
  <div class="wrap shoptrack">
    <?= \App\Core\View::partial('vitrine/partials/flash', ['flash' => $flash]) ?>
    <?php if (in_array($o['status'], ['canceled', 'refunded'], true)): ?>
      <p class="alert"><?= e(Orders::STATUSES[$o['status']]) ?>.</p>
    <?php elseif ($o['status'] === 'pending'): ?>
      <p class="alert">Le paiement n’est pas encore confirmé. S’il vient d’être fait, rechargez la page dans un instant.</p>
    <?php else: ?>
    <ol class="shopsteps">
      <?php foreach (Orders::STEPS as $k => $label): $i = array_search($k, $steps, true); ?>
      <li class="<?= $i < $cur ? 'is-done' : ($i === $cur ? 'is-now' : '') ?>"><span><?= $i + 1 ?></span><?= e($label) ?></li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>
    <?php if ($o['tracking']['number'] !== ''): ?>
      <p class="shoptrack__ship">📦 <?= e($o['tracking']['carrier'] ?: 'Colis') ?> · n° <b><?= e($o['tracking']['number']) ?></b><?php if ($o['tracking']['url'] !== ''): ?> · <a href="<?= e($o['tracking']['url']) ?>" rel="noopener" target="_blank">suivre le colis</a><?php endif; ?></p>
    <?php endif; ?>
    <div class="shopcart">
      <?php foreach ($o['items'] as $n => $it): ?>
      <div class="shopline"><div class="shopline__img"><?= $previews[$n] ?? '' ?></div><div class="shopline__txt"><b><?= (int) $it['qty'] ?> × <?= e($it['name']) ?></b> <span class="muted">(<?= e($it['support']) ?>)</span><br><small><?= e(Orders::describe($it)) ?></small></div><b class="shopline__price"><?= e(Orders::money($it['total'])) ?></b></div>
      <?php endforeach; ?>
      <div class="shoptotal"><span>Livraison</span><b><?= e($o['shipping'] ? Orders::money($o['shipping']) : 'offerte') ?></b><span class="shoptotal__all">Total</span><b class="shoptotal__all"><?= e(Orders::money($o['total'])) ?></b></div>
    </div>
    <p class="small">Livraison : <?= e($o['customer']['name'] . ', ' . $o['customer']['line1'] . ', ' . $o['customer']['zip'] . ' ' . $o['customer']['city']) ?>.</p>

    <h2 class="h-2" id="messages">Une question ?</h2>
    <p>Votre commande est fabriquée par notre imprimeur<?= $config['printer_name'] !== '' ? ', ' . e($config['printer_name']) : '' ?> : il vous répond directement, par e-mail et sur cette page.</p>
    <?php foreach ($o['messages'] as $msg): ?>
      <div class="shopmsg shopmsg--<?= e($msg['from']) ?>"><small><?= e($msg['from'] === 'client' ? 'Vous' : ($msg['from'] === 'imprimeur' ? 'L’imprimeur' : 'L’association')) ?> · <?= e(date('d/m/Y H:i', strtotime($msg['at']))) ?></small><p><?= nl2br(e($msg['text'])) ?></p></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(Host::url('/boutique/commande/' . $o['token'] . '/')) ?>" class="vform shopmsgform">
      <?= csrf_field() ?>
      <div class="hp" aria-hidden="true"><label for="tm-w">Ne pas remplir</label><input type="text" id="tm-w" name="website" tabindex="-1" autocomplete="off"></div>
      <div class="field"><label for="tm-text">Votre message</label><textarea id="tm-text" name="text" rows="4" required maxlength="2000"></textarea></div>
      <button class="btn btn--navy">Envoyer</button>
    </form>
  </div>
</section>
