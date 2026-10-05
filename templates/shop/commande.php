<?php
/**
 * Fiche d'une commande, commune au back-office (association) et à l'espace imprimeur.
 * Variables : $o, $previews (SVG par article), $base (adresse de la fiche, actions en POST),
 * $who ('association' ou 'imprimeur').
 */
use App\Shop\Orders;

$c = $o['customer'];
$admin = $who === 'association';
$next = ['paid' => 'production', 'production' => 'shipped', 'shipped' => 'delivered'][$o['status']] ?? null;
?>
<div class="shoporder">
  <div class="shoporder__main">
    <section class="card card--pad">
      <div class="card__head"><h2 class="card__t">Commande <?= e($o['id']) ?></h2><span class="shopst shopst--<?= e($o['status']) ?>"><?= e(Orders::STATUSES[$o['status']]) ?></span></div>
      <p class="small muted" style="margin:0 0 10px">Passée le <?= e(date('d/m/Y à H:i', strtotime($o['created']))) ?><?= !empty($o['paid_at']) ? ' · payée le ' . e(date('d/m/Y à H:i', strtotime($o['paid_at']))) : '' ?></p>
      <?php foreach ($o['items'] as $n => $it): ?>
      <div class="shopitem">
        <div class="shopitem__img"><?= $previews[$n] ?? '' ?></div>
        <div class="shopitem__txt">
          <b><?= (int) $it['qty'] ?> × <?= e($it['name']) ?></b> <span class="muted">(<?= e($it['support']) ?>)</span><br>
          <span class="small"><?= e(Orders::describe($it)) ?></span><br>
          <span class="small"><?= e(Orders::money($it['unit'])) ?> l’unité · <?= e(Orders::money($it['total'])) ?></span>
        </div>
        <?php if (in_array($o['status'], ['paid', 'production', 'shipped', 'delivered'], true)): ?>
        <a class="btn btn--sm btn--yellow" href="<?= e($base) ?>/pdf/<?= $n + 1 ?>">PDF d’impression</a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <p class="small" style="margin:10px 0 0;text-align:right"><?php if ((int) ($o['discount'] ?? 0) > 0): ?>Code promo <?= e($o['promo']['code'] ?? '') ?> : −<?= e(Orders::money((int) $o['discount'])) ?> · <?php endif; ?>Livraison : <?= e($o['shipping'] ? Orders::money($o['shipping']) : 'offerte') ?> · <b>Total : <?= e(Orders::money($o['total'])) ?></b><?= $o['refunded'] ? ' · remboursé : ' . e(Orders::money($o['refunded'])) : '' ?></p>
      <?php if ($admin && isset($o['fee'])): ?><p class="xs muted" style="margin:2px 0 0;text-align:right">Frais Stripe : <?= e(Orders::money((int) $o['fee'])) ?> · net encaissé : <?= e(Orders::money((int) ($o['net'] ?? 0))) ?></p><?php endif; ?>
      <?php if ($admin && !empty($o['dispute'])): ?><p class="alert alert--error" style="margin:8px 0 0">Litige Stripe : <?= e($o['dispute']['status']) ?> (<?= e($o['dispute']['reason']) ?>), <?= e(Orders::money((int) $o['dispute']['amount'])) ?>. Répondez dans le tableau de bord Stripe avec la preuve d’expédition.</p><?php endif; ?>
    </section>

    <section class="card card--pad" id="messages">
      <h2 class="card__t card__t--sm">Messages avec le client</h2>
      <?php if (!$o['messages']): ?><p class="small muted">Aucun message. Le client peut écrire depuis sa page de suivi ; <?= $admin ? 'l’imprimeur lui répond (service client).' : 'vous lui répondez ici (il reçoit un e-mail).' ?></p><?php endif; ?>
      <?php foreach ($o['messages'] as $msg): ?>
      <div class="shopmsg shopmsg--<?= e($msg['from']) ?>"><span class="xs muted"><?= e(['client' => $c['name'], 'imprimeur' => 'Imprimeur', 'association' => 'Association'][$msg['from']]) ?> · <?= e(date('d/m/Y H:i', strtotime($msg['at']))) ?></span><p><?= nl2br(e($msg['text'])) ?></p></div>
      <?php endforeach; ?>
      <form method="post" action="<?= e($base) ?>" style="margin-top:10px">
        <?= csrf_field() ?><input type="hidden" name="action" value="message">
        <label class="f"><span class="f__k">Répondre au client</span><textarea class="in" name="text" rows="3" required maxlength="2000"></textarea></label>
        <div class="row" style="justify-content:flex-end"><button class="btn btn--navy btn--sm">Envoyer</button></div>
      </form>
    </section>
  </div>

  <aside class="shoporder__side">
    <section class="card card--pad">
      <h2 class="card__t card__t--sm">Livraison</h2>
      <p class="small" style="margin:0"><b><?= e($c['name']) ?></b><br><?= e($c['line1']) ?><?= $c['line2'] !== '' ? '<br>' . e($c['line2']) : '' ?><br><?= e($c['zip'] . ' ' . $c['city']) ?><br><?= e(Orders::COUNTRIES[$c['country']] ?? $c['country']) ?></p>
      <p class="small" style="margin:8px 0 0"><?= e($c['email']) ?><?= $c['phone'] !== '' ? '<br>' . e($c['phone']) : '' ?></p>
    </section>

    <?php if (!in_array($o['status'], ['pending', 'canceled', 'refunded'], true)): ?>
    <section class="card card--pad">
      <h2 class="card__t card__t--sm">Étape</h2>
      <form method="post" action="<?= e($base) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="status">
        <label class="f"><span class="f__k">Nouvelle étape</span><select class="in" name="status">
          <?php foreach (['paid', 'production', 'shipped', 'delivered'] as $k): ?><option value="<?= $k ?>"<?= $k === ($next ?? $o['status']) ? ' selected' : '' ?>><?= e(Orders::STATUSES[$k]) ?></option><?php endforeach; ?>
          <?php if ($admin): ?><option value="canceled">Annulée</option><?php endif; ?>
        </select></label>
        <div class="pgrid">
          <label class="f"><span class="f__k">Transporteur</span><input class="in in--sm" name="carrier" value="<?= e($o['tracking']['carrier']) ?>" placeholder="La Poste, Mondial Relay…"></label>
          <label class="f"><span class="f__k">N° de suivi</span><input class="in in--sm" name="number" value="<?= e($o['tracking']['number']) ?>"></label>
        </div>
        <label class="f"><span class="f__k">Lien de suivi (https://…)</span><input class="in in--sm" name="url" value="<?= e($o['tracking']['url']) ?>"></label>
        <label class="f"><span class="f__k">Note (visible dans l’historique)</span><input class="in in--sm" name="note"></label>
        <label class="toggle"><input type="checkbox" name="notify" value="1" checked><span class="toggle__box"></span><span>Prévenir le client par e-mail</span></label>
        <div class="row" style="justify-content:flex-end;margin-top:6px"><button class="btn btn--navy btn--sm">Enregistrer l’étape</button></div>
      </form>
    </section>
    <?php endif; ?>

    <?php if ($admin && $o['status'] === 'pending'): ?>
    <section class="card card--pad">
      <h2 class="card__t card__t--sm">Paiement</h2>
      <p class="small">En attente du paiement en ligne. Réglé autrement (chèque, espèces) ?</p>
      <form method="post" action="<?= e($base) ?>" data-confirm="Paiement reçu ?|La commande passera « payée » et partira chez l’imprimeur.|Confirmer">
        <?= csrf_field() ?><input type="hidden" name="action" value="paid"><button class="btn btn--ghost btn--sm">Paiement reçu hors ligne</button>
      </form>
    </section>
    <?php endif; ?>

    <?php if ($admin && $o['paid'] > $o['refunded']): ?>
    <section class="card card--pad">
      <h2 class="card__t card__t--sm">Rembourser</h2>
      <form method="post" action="<?= e($base) ?>" data-confirm="Rembourser ?|Le montant est rendu au client sur sa carte (Stripe) et il est prévenu par e-mail.|Rembourser|danger">
        <?= csrf_field() ?><input type="hidden" name="action" value="refund">
        <label class="f"><span class="f__k">Montant (€, vide : tout le reste, <?= e(Orders::money($o['paid'] - $o['refunded'])) ?>)</span><input class="in in--sm" type="number" step="0.01" min="0" name="amount"></label>
        <div class="row" style="justify-content:flex-end"><button class="btn btn--ghost btn--sm">Rembourser</button></div>
      </form>
    </section>
    <?php endif; ?>

    <section class="card card--pad">
      <h2 class="card__t card__t--sm">Historique</h2>
      <ol class="shophist">
        <?php foreach (array_reverse($o['history']) as $h): ?>
        <li><b><?= e(Orders::STATUSES[$h['status']] ?? $h['status']) ?></b> <span class="xs muted"><?= e(date('d/m/Y H:i', strtotime($h['at']))) ?> · <?= e($h['by']) ?></span><?= $h['note'] !== '' ? '<br><span class="small">' . e($h['note']) . '</span>' : '' ?></li>
        <?php endforeach; ?>
      </ol>
      <?php if ($admin): ?><p class="xs muted" style="margin:6px 0 0">Page de suivi du client : <a href="<?= e(Orders::trackingUrl($o)) ?>" target="_blank">ouvrir</a></p><?php endif; ?>
    </section>
  </aside>
</div>
