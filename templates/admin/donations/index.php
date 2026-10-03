<?php
/**
 * Dons. Variables : $rows, $total, $page, $pages, $status, $mode, $q, $kpi, $gauge, $wall, $enabled, $test, $methods, $receipts
 */
use App\Admin\Base;
use App\Core\Auth;
use App\Front\Donations as Front;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$qs = fn (array $o) => '?' . http_build_query(array_filter(array_merge(['statut' => $status, 'mode' => $mode, 'q' => $q], $o), fn ($v) => $v !== '' && $v !== null));
$pill = ['paid' => 'ok', 'active' => 'ok', 'pending' => 'warn', 'canceled' => 'info', 'failed' => 'ko', 'refunded' => 'ko', 'abandoned' => 'brouillon'];
?>
<?php if (!$enabled): ?>
  <p class="alert" style="margin:0">Les dons en ligne sont <b>désactivés</b> : la page « Faire un don » présente la collecte sans formulaire de paiement.<?= Auth::isAdmin() ? ' <a href="/admin/reglages?groupe=donations">Activer et régler Stripe / PayPal</a>' : '' ?></p>
<?php elseif ($test): ?>
  <p class="alert alert--info" style="margin:0"><b>Mode test</b> : aucun prélèvement réel (cartes de test Stripe, comptes bac à sable PayPal). Moyens actifs : <?= e(implode(', ', $methods) ?: 'aucun (clés manquantes)') ?>.</p>
<?php endif; ?>

<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= $fmt($gauge['raised']) ?> €</b><span>collectés · <?= e($gauge['label']) ?></span><small>objectif <?= $fmt($gauge['goal']) ?> € (<?= e((string) $gauge['pct']) ?> %)</small></div>
  <div class="kpi"><b><?= e(Front::money($kpi['month'])) ?></b><span>ce mois-ci</span><small><?= e(Front::money($kpi['year'])) ?> depuis janvier</small></div>
  <div class="kpi"><b><?= (int) $kpi['active'] ?></b><span>dons mensuels actifs</span><small><?= e(Front::money($kpi['monthly'])) ?> chaque mois</small></div>
  <div class="kpi"><b><?= $fmt($gauge['donors']) ?></b><span>donateurs</span><small><?= count($wall['names']) ?> nom(s) sur le mur · <?= (int) $wall['anonymous'] ?> discret(s)</small></div>
  <?php if ($receipts): ?><div class="kpi"><b><?= (int) $kpi['receipts'] ?></b><span>reçus fiscaux émis</span><small>numérotation RF-AAAA-0001</small></div><?php endif; ?>
</div>
<div class="bar bar--navy" style="height:16px"><i style="width:<?= min(100, (float) $gauge['pct']) ?>%"></i></div>

<div class="toolbar">
  <div class="chips">
    <?php foreach (['' => 'Tous', 'paid' => 'Payés', 'mensuels' => 'Mensuels', 'pending' => 'En attente', 'failed' => 'Échecs', 'refunded' => 'Remboursés', 'abandoned' => 'Abandonnés', 'mur' => 'Mur des donateurs'] as $k => $l): ?>
      <a class="chip<?= $status === $k ? ' is-on' : '' ?>" href="/admin/dons<?= e($qs(['statut' => $k, 'page' => null])) ?>"><?= e($l) ?></a>
    <?php endforeach; ?>
  </div>
  <span class="grow"></span>
  <a class="btn" href="/admin/dons/export.csv">Export CSV</a>
  <a class="btn" href="/admin/dons/export.csv?annee=<?= date('Y') - 1 ?>">Export <?= date('Y') - 1 ?></a>
  <a class="btn" href="/admin/collection/dons">Textes de la page</a>
</div>
<form class="toolbar" method="get" action="/admin/dons">
  <?php if ($status !== ''): ?><input type="hidden" name="statut" value="<?= e($status) ?>"><?php endif; ?>
  <div class="search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, e-mail, n° de don…" aria-label="Rechercher un don"><button type="submit">→</button></div>
  <select name="mode" aria-label="Mode" data-autosubmit><?php foreach (['live' => 'Production', 'test' => 'Test', 'tous' => 'Test et production'] as $k => $l): ?><option value="<?= $k ?>"<?= $mode === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  <span class="small muted"><?= $fmt($total) ?> don<?= $total > 1 ? 's' : '' ?></span>
</form>

<div class="table">
  <table>
    <thead><tr><th>Date</th><th>Donateur</th><th>Montant</th><th>Moyen</th><th>Statut</th><th>Mur</th><th>Reçu</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $d): $paid = array_sum(array_map(fn ($p) => $p['status'] === 'paid' ? (int) $p['amount'] : 0, $d['payments'] ?? [])); ?>
      <tr data-href="/admin/dons/<?= e($d['id']) ?>">
        <td class="xs nowrap"><?= e(date('d/m/Y H:i', strtotime((string) ($d['last_paid'] ?? $d['created'])))) ?></td>
        <td><a class="rowlink" href="/admin/dons/<?= e($d['id']) ?>"><?= e(trim(($d['donor']['first'] ?? '') . ' ' . ($d['donor']['last'] ?? '')) ?: '—') ?></a><br><span class="xs muted"><?= e($d['donor']['email'] ?? '') ?></span></td>
        <td class="t-num"><?= e(Front::money((int) $d['amount'])) ?><?= $d['frequency'] === 'month' ? ' <span class="xs">/ mois</span>' : '' ?><?= $d['frequency'] === 'month' && $paid ? '<br><span class="xs muted">' . e(Front::money($paid)) . ' versés</span>' : '' ?></td>
        <td class="small"><?= e($d['provider'] === 'manuel' && !empty($d['method_detail']) ? ucfirst((string) $d['method_detail']) : (Front::PROVIDERS[$d['provider']] ?? $d['provider'])) ?><?= ($d['mode'] ?? '') === 'test' ? ' <span class="pill pill--info">test</span>' : '' ?></td>
        <td><span class="pill pill--<?= $pill[$d['status']] ?? 'warn' ?>"><?= e(Front::STATUS[$d['status']] ?? $d['status']) ?></span></td>
        <td class="small"><?= !empty($d['wall']) ? (!empty($d['wall_hidden']) ? '<s class="muted">' . e($d['wall_name']) . '</s>' : e($d['wall_name'])) : '<span class="muted">discret</span>' ?></td>
        <td class="small"><?= e(implode(', ', Front::receiptNumbers($d))) ?: ($d['receipt'] ? '<span class="warn">demandé</span>' : '<span class="muted">—</span>') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="padding:24px;text-align:center">Aucun don.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pagination"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page ? 'is-on' : '' ?>" href="/admin/dons<?= e($qs(['page' => $i])) ?>"><?= $i ?></a><?php endfor; ?></nav>
<?php endif; ?>

<div class="cols cols--wide">
  <?php if (!Auth::isAdmin()): ?>
  <div class="card card--pad">
    <h2 class="card__t card__t--sm">Dons reçus hors ligne</h2>
    <p class="small muted" style="margin:0">Les dons par chèque, virement ou espèces<?= $receipts ? ' et les reçus fiscaux' : '' ?> sont enregistrés par un administrateur : ils apparaissent ensuite dans la liste et dans la jauge.</p>
  </div>
  <?php else: ?>
  <form class="card card--pad" method="post" action="/admin/dons">
    <?= csrf_field() ?><input type="hidden" name="action" value="manuel">
    <h2 class="card__t card__t--sm">Enregistrer un don hors ligne</h2>
    <p class="small muted" style="margin:0">Chèque, virement ou espèces : le don rejoint la jauge et, si vous le souhaitez, le mur des donateurs. Réservé aux administrateurs.</p>
    <div class="fgrid">
      <label class="f"><span class="f__k">Montant (€) <b>*</b></span><input type="text" name="amount" inputmode="decimal" required placeholder="50"></label>
      <label class="f"><span class="f__k">Date</span><input type="date" name="date" value="<?= date('Y-m-d') ?>"></label>
      <label class="f"><span class="f__k">Moyen</span><select name="method"><option>chèque</option><option>virement</option><option>espèces</option><option>autre</option></select></label>
      <label class="f"><span class="f__k">Prénom ou organisme <b>*</b></span><input type="text" name="first" required maxlength="80"></label>
      <label class="f"><span class="f__k">Nom</span><input type="text" name="last" maxlength="80"></label>
      <label class="f"><span class="f__k">E-mail</span><input type="email" name="email" maxlength="160"></label>
      <label class="f f--2"><span class="f__k">Adresse <i>pour un reçu fiscal</i></span><input type="text" name="address" maxlength="200"></label>
      <label class="f"><span class="f__k">Code postal</span><input type="text" name="zip" maxlength="12"></label>
      <label class="f"><span class="f__k">Ville</span><input type="text" name="city" maxlength="80"></label>
      <label class="f"><span class="f__k">Nom sur le mur <i>vide = discret</i></span><input type="text" name="wall_name" maxlength="40"></label>
    </div>
    <div class="row">
      <label class="toggle"><input type="checkbox" name="thank" value="1"><span class="toggle__box"></span><span>Envoyer l’e-mail de remerciement</span></label>
      <?php if ($receipts): ?><label class="toggle"><input type="checkbox" name="receipt" value="1"><span class="toggle__box"></span><span>Émettre un reçu fiscal</span></label><?php endif; ?>
    </div>
    <div class="f"><span class="f__k">Note interne</span><textarea name="note" rows="2" data-wysiwyg="mini"></textarea></div>
    <button type="submit" class="btn btn--navy" style="align-self:flex-start">Enregistrer le don</button>
  </form>
  <?php endif; ?>
  <div class="stack">
    <div class="card">
      <div class="card__head"><h2 class="card__t card__t--sm">Mur des donateurs</h2><a class="linkbtn" href="/faire-un-don/#mur" target="_blank" rel="noopener">Voir ↗</a></div>
      <div class="card__body"><p class="small" style="margin:0"><?= $wall['names'] ? e(implode(' · ', $wall['names'])) : '<span class="muted">Aucun nom pour l’instant.</span>' ?><?= $wall['anonymous'] ? ' <span class="muted">+ ' . (int) $wall['anonymous'] . ' donateur(s) discret(s)</span>' : '' ?></p><span class="xs muted">Pour masquer ou corriger un nom, ouvrez le don correspondant (filtre « Mur des donateurs »).</span></div>
    </div>
    <div class="card card--pad">
      <h2 class="card__t card__t--sm">Maintenance</h2>
      <div class="row">
        <form method="post" action="/admin/dons"><?= csrf_field() ?><button type="submit" name="action" value="synchroniser" class="btn btn--sm">Synchroniser Stripe / PayPal</button></form>
        <form method="post" action="/admin/dons"><?= csrf_field() ?><button type="submit" name="action" value="recalculer" class="btn btn--sm">Recalculer la jauge</button></form>
      </div>
      <span class="xs muted">La synchronisation passe aussi automatiquement toutes les heures (tâches planifiées).</span>
    </div>
  </div>
</div>
