<?php
/** Une adhésion. Variables : $a */
use App\Admin\Base;
use App\Vitrine\Membership;

$m = $a['member'];
$s = $a['status'];
?>
<div class="cols cols--wide">
  <div class="stack">
    <div class="card">
      <div class="card__head"><h2 class="card__t"><?= e(trim($m['first'] . ' ' . $m['last'])) ?></h2><span class="pill pill--<?= ['paid' => 'ok', 'offline' => 'warn', 'pending' => 'info'][$s] ?? 'ko' ?>"><?= e(Membership::STATUS[$s] ?? $s) ?></span></div>
      <div class="card__body">
        <div class="fgrid">
          <div class="f"><span class="f__k">Formule</span><span><?= e($a['label']) ?> · <b><?= e(Membership::money((int) $a['amount'])) ?></b> · <?= (int) $a['year'] ?></span></div>
          <div class="f"><span class="f__k">Règlement</span><span><?= e(Membership::PROVIDERS[$a['provider']] ?? $a['provider']) ?><?= ($a['mode'] ?? '') === 'test' && in_array($a['provider'], ['stripe', 'paypal'], true) ? ' (mode test)' : '' ?></span></div>
          <div class="f"><span class="f__k">Coordonnées</span><span><?= $m['email'] !== '' ? '<a href="mailto:' . e($m['email']) . '">' . e($m['email']) . '</a>' : '<span class="muted">pas d’e-mail</span>' ?><?= !empty($m['phone']) ? '<br>' . e($m['phone']) : '' ?></span></div>
          <div class="f"><span class="f__k">Adresse</span><span><?= e(trim(($m['address'] ?? '') . ' ' . ($m['zip'] ?? '') . ' ' . ($m['city'] ?? ''))) ?: '<span class="muted">—</span>' ?></span></div>
          <?php if (!empty($m['family'])): ?><div class="f f--full"><span class="f__k">Famille</span><span><?= e($m['family']) ?></span></div><?php endif; ?>
          <div class="f"><span class="f__k">Reçue</span><span><?= e(date('d/m/Y à H:i', strtotime((string) $a['created']))) ?><br><span class="xs muted"><?= e(['site' => 'formulaire du site', 'apercu' => 'essai depuis l’aperçu', 'back-office' => 'saisie dans le back-office'][$a['origin'] ?? 'site'] ?? '') ?></span></span></div>
          <div class="f"><span class="f__k">Newsletter</span><span><?= !empty($a['newsletter']) ? 'inscription demandée' : 'non' ?></span></div>
          <?php if (!empty($a['source'])): ?><div class="f f--full"><span class="f__k">A connu l’association par</span><span><?= e($a['source']) ?></span></div><?php endif; ?>
          <div class="f f--full"><span class="f__k">Référence</span><span class="small"><?= e($a['id']) ?></span></div>
        </div>
        <?php if (!empty($a['payments'])): ?>
          <div class="small"><b>Paiements :</b> <?php foreach ($a['payments'] as $p): ?><?= e(Membership::money((int) $p['amount'])) ?> le <?= e(date('d/m/Y', strtotime((string) $p['at']))) ?> <span class="xs muted">(<?= e($p['ref']) ?><?= !empty($p['by']) ? ', ' . e($p['by']) : '' ?>)</span> <?php endforeach; ?></div>
        <?php endif; ?>
        <?php if (!empty($a['error'])): ?><p class="alert alert--error" style="margin:0">Erreur du prestataire : <?= e($a['error']) ?></p><?php endif; ?>
      </div>
    </div>
    <form class="card card--pad" method="post" action="/admin/association/adhesions/<?= e($a['id']) ?>">
      <?= csrf_field() ?><input type="hidden" name="do" value="note">
      <div class="f f--full"><span class="f__k"><label for="ad-note">Note interne</label></span><textarea id="ad-note" name="note" rows="3"><?= e((string) ($a['note'] ?? '')) ?></textarea></div>
      <div class="row"><button type="submit" class="btn btn--sm">Enregistrer la note</button></div>
    </form>
  </div>
  <div class="stack">
    <?php if (in_array($s, ['offline', 'pending', 'abandoned', 'failed'], true)): ?>
      <form class="card card--pad" method="post" action="/admin/association/adhesions/<?= e($a['id']) ?>">
        <?= csrf_field() ?><input type="hidden" name="do" value="paye">
        <h2 class="card__t">Règlement reçu</h2>
        <div class="fgrid">
          <div class="f"><span class="f__k"><label for="pay-p">Moyen</label></span><select id="pay-p" name="provider"><?php foreach (['cheque', 'especes', 'virement', 'helloasso', 'autre'] as $k): ?><option value="<?= e($k) ?>"><?= e(Membership::PROVIDERS[$k]) ?></option><?php endforeach; ?></select></div>
          <div class="f"><span class="f__k"><label for="pay-r">Référence</label> <i>n° de chèque…</i></span><input id="pay-r" type="text" name="ref" maxlength="60"></div>
        </div>
        <label class="toggle"><input type="checkbox" name="welcome" value="1"<?= $m['email'] !== '' ? ' checked' : ' disabled' ?>><span class="toggle__box"></span><span>Envoyer l’e-mail de bienvenue</span></label>
        <div class="row"><button type="submit" class="btn btn--navy">Marquer comme payée</button></div>
      </form>
    <?php endif; ?>
    <div class="card card--pad">
      <h2 class="card__t">Actions</h2>
      <?php if ($s === 'paid' && $m['email'] !== ''): ?>
        <form method="post" action="/admin/association/adhesions/<?= e($a['id']) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="bienvenue"><button type="submit" class="btn btn--sm">Renvoyer l’e-mail de bienvenue</button></form>
      <?php endif; ?>
      <?php if (!in_array($s, ['canceled', 'refunded'], true)): ?>
        <form method="post" action="/admin/association/adhesions/<?= e($a['id']) ?>" data-confirm="Annuler cette adhésion ?|Elle reste dans la liste, marquée « Annulée ». Un paiement en ligne n’est pas remboursé automatiquement (à faire chez Stripe ou PayPal).|Annuler l’adhésion|danger"><?= csrf_field() ?><input type="hidden" name="do" value="annuler"><button type="submit" class="btn btn--sm btn--ghost">Annuler l’adhésion</button></form>
      <?php endif; ?>
      <form method="post" action="/admin/association/adhesions/<?= e($a['id']) ?>" data-confirm="Supprimer définitivement ?|L’adhésion et les coordonnées de l’adhérent seront effacées.|Supprimer|danger"><?= csrf_field() ?><input type="hidden" name="do" value="supprimer"><button type="submit" class="btn btn--sm btn--ghost">Supprimer</button></form>
      <a class="linkbtn" href="/admin/association/adhesions?annee=<?= (int) $a['year'] ?>">← Toutes les adhésions</a>
    </div>
  </div>
</div>
