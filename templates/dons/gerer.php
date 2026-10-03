<?php
/** Espace du donateur (lien personnel reçu par e-mail). Variables : $don, $flash, $token */
use App\Front\Donations;

$monthly = $don['frequency'] === 'month';
$paid = array_values(array_filter($don['payments'] ?? [], fn ($p) => in_array($p['status'], ['paid', 'refunded'], true)));
$statusLabel = [
    'pending' => t('En attente de paiement'), 'paid' => t('Don reçu'), 'active' => t('Don mensuel actif'), 'canceled' => t('Don mensuel arrêté'),
    'failed' => t('Paiement non abouti'), 'refunded' => t('Remboursé'), 'abandoned' => t('Paiement non finalisé'),
][$don['status']] ?? $don['status'];
$base = url('/faire-un-don/gerer/' . $token . '/');
?>
<section class="dmerci bg-navy">
  <div class="wrap dmerci__in">
    <span class="eyebrow eyebrow--lg"><?= e(t('Mon don')) ?> · <?= e($don['id']) ?></span>
    <h1 class="dmerci__title"><?= e(Donations::money((int) $don['amount'])) ?><?php if ($monthly): ?> <span class="yellow"><?= e(t('par mois')) ?></span><?php endif; ?></h1>
    <dl class="dmerci__facts">
      <div><dt><?= e(t('Statut')) ?></dt><dd><?= e($statusLabel) ?></dd></div>
      <div><dt><?= e(t('Paiement')) ?></dt><dd><?= e(t(Donations::PROVIDERS[$don['provider']] ?? $don['provider'])) ?></dd></div>
      <div><dt><?= e($monthly ? t('Depuis le') : t('Le')) ?></dt><dd><?= e(date_num(substr((string) ($don['started'] ?? $don['created']), 0, 10))) ?></dd></div>
    </dl>
  </div>
</section>
<section class="wrap section dmanage">
  <?php if ($flash): ?><p class="alert alert--<?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= e($flash['msg']) ?></p><?php endif; ?>
  <div class="dmanage__grid">
    <div class="stack" style="gap:18px">
      <h2 class="h-2"><?= e($monthly ? t('Mes versements') : t('Mon versement')) ?></h2>
      <?php if ($paid): ?>
      <table class="dtable">
        <thead><tr><th><?= e(t('Date')) ?></th><th><?= e(t('Montant')) ?></th><th><?= e(t('État')) ?></th><th><?= e(t('Reçu fiscal')) ?></th></tr></thead>
        <tbody>
          <?php foreach (array_reverse($paid) as $p): ?>
            <tr>
              <td><?= e(date_num(substr($p['at'], 0, 10))) ?></td>
              <td><?= e(Donations::money((int) $p['amount'])) ?></td>
              <td><?= e($p['status'] === 'refunded' ? t('Remboursé') : t('Reçu')) ?></td>
              <td><?php if (!empty($p['receipt'])): ?><a href="<?= e($base . 'recu/' . $p['receipt'] . '/') ?>"><?= e($p['receipt']) ?> ↓</a><?php else: ?>—<?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p class="lead" style="max-width:none"><?= e(t('Aucun versement confirmé pour le moment.')) ?></p>
      <?php endif; ?>
      <?php if ($monthly && $don['status'] === 'active'): ?>
        <form method="post" action="<?= e($base) ?>" class="dmanage__stop" data-confirm="<?= e(t('Arrêter votre don mensuel ? Aucun nouveau prélèvement ne sera effectué.')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="stop">
          <p><?= e(t('Vous pouvez arrêter votre don mensuel à tout moment, sans frais ni justification.')) ?></p>
          <button type="submit" class="btn btn--ghost"><?= e(t('Arrêter mon don mensuel')) ?></button>
        </form>
      <?php endif; ?>
    </div>
    <form method="post" action="<?= e($base) ?>" class="dcard dmanage__wall">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="wall">
      <span class="dstep2__title"><?= e(t('Le mur des donateurs')) ?></span>
      <label class="ccheck"><input type="checkbox" name="wall" value="1"<?= !empty($don['wall']) ? ' checked' : '' ?>> <span><?= e(t('Afficher mon nom sur le mur des donateurs')) ?></span></label>
      <label class="cfield"><span><?= e(t('Nom affiché')) ?></span><input name="wall_name" maxlength="40" value="<?= e($don['wall_name'] ?? '') ?>" placeholder="<?= e(t('ex. Famille M., Un Lionceau depuis 1981')) ?>"></label>
      <?php if (!empty($don['wall_hidden'])): ?><p class="dcard__closed"><?= e(t('Ce nom a été masqué par l’équipe du musée.')) ?></p><?php endif; ?>
      <button type="submit" class="dcta dcta--sm"><?= e(t('Enregistrer')) ?></button>
      <p class="dcard__rgpd"><?= e(t('Pour toute question sur votre don ou pour exercer vos droits sur vos données :')) ?> <a href="<?= e(url('/contact/') . '?objet=autre') ?>"><?= e(t('nous écrire')) ?></a></p>
    </form>
  </div>
</section>
