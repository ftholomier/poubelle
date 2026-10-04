<?php
/** Adhésions. Variables : $rows, $year, $years, $status, $count, $amount, $tariffs, $online */
use App\Admin\Base;
use App\Vitrine\Membership;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
?>
<div class="toolbar">
  <div class="chips">
    <?php foreach ($years as $y): ?><a class="chip<?= $y === $year ? ' is-on' : '' ?>" href="/admin/association/adhesions?annee=<?= (int) $y ?>"><?= (int) $y ?></a><?php endforeach; ?>
  </div>
  <div class="chips">
    <?php foreach (['' => 'Toutes', 'paid' => 'Payées', 'offline' => 'Règlement attendu', 'pending' => 'Paiement en cours', 'abandoned' => 'Abandonnées', 'canceled' => 'Annulées'] as $k => $l): ?><a class="chip<?= $status === $k ? ' is-on' : '' ?>" href="/admin/association/adhesions?annee=<?= (int) $year ?><?= $k !== '' ? '&amp;statut=' . e($k) : '' ?>"><?= e($l) ?></a><?php endforeach; ?>
  </div>
  <span class="grow"></span>
  <a class="btn" href="/admin/association/adhesions/export.csv?annee=<?= (int) $year ?>">Exporter <?= (int) $year ?> (CSV)</a>
  <a class="btn btn--navy" href="#ajouter">+ Adhésion papier</a>
</div>
<p class="small" style="margin:0"><b><?= $fmt($count) ?> adhérent<?= $count > 1 ? 's' : '' ?> en <?= (int) $year ?></b> (adhésions payées) · <?= e(Membership::money($amount)) ?> de cotisations · paiement en ligne : <?= $online ? e(implode(', ', $online)) : '<span class="ko">indisponible</span> (<a href="/admin/reglages?groupe=donations">Réglages › Dons</a>)' ?></p>
<div class="table">
  <table>
    <thead><tr><th>Reçue</th><th>Adhérent</th><th>Formule</th><th>Montant</th><th>Règlement</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $a): $m = $a['member']; ?>
      <tr data-href="/admin/association/adhesions/<?= e($a['id']) ?>">
        <td class="xs nowrap"><?= e(Base::ago($a['created'])) ?></td>
        <td><a class="rowlink" href="/admin/association/adhesions/<?= e($a['id']) ?>"><b><?= e(trim($m['first'] . ' ' . $m['last'])) ?></b></a><br><span class="xs muted"><?= e($m['email'] ?: '—') ?><?= ($a['origin'] ?? '') === 'apercu' ? ' · essai depuis l’aperçu' : '' ?></span></td>
        <td class="small"><?= e($a['label']) ?></td>
        <td class="nowrap"><?= e(Membership::money((int) $a['amount'])) ?></td>
        <td class="small"><?= e(Membership::PROVIDERS[$a['provider']] ?? $a['provider']) ?><?= ($a['mode'] ?? '') === 'test' && in_array($a['provider'], ['stripe', 'paypal'], true) ? ' <span class="pill">test</span>' : '' ?></td>
        <td><span class="pill pill--<?= ['paid' => 'ok', 'offline' => 'warn', 'pending' => 'info'][$a['status']] ?? 'ko' ?>"><?= e(Membership::STATUS[$a['status']] ?? $a['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" style="padding:28px;text-align:center" class="muted">Aucune adhésion<?= $status !== '' ? ' de ce type' : '' ?> en <?= (int) $year ?>.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<form class="card card--pad" id="ajouter" method="post" action="/admin/association/adhesions">
  <?= csrf_field() ?>
  <h2 class="card__t">Ajouter une adhésion reçue sur papier</h2>
  <p class="small muted" style="margin:0">Bulletin rempli, chèque, espèces, virement ou adhésion HelloAsso : l’adhérent rejoint la liste (et l’export).</p>
  <div class="fgrid fgrid--2">
    <div class="f"><span class="f__k"><label for="ad-t">Formule</label></span><select id="ad-t" name="tariff"><?php foreach ($tariffs as $t): ?><option value="<?= e($t['key']) ?>"><?= e($t['label']) ?> · <?= (int) $t['amount'] ?> €</option><?php endforeach; ?></select></div>
    <div class="f"><span class="f__k"><label for="ad-a">Montant réglé (€)</label> <i>si supérieur à la formule</i></span><input id="ad-a" type="text" inputmode="numeric" name="amount"></div>
    <div class="f"><span class="f__k"><label for="ad-f">Prénom</label></span><input id="ad-f" type="text" name="first" maxlength="60"></div>
    <div class="f"><span class="f__k"><label for="ad-l">Nom</label> <b aria-hidden="true">*</b></span><input id="ad-l" type="text" name="last" maxlength="80" required></div>
    <div class="f"><span class="f__k"><label for="ad-e">E-mail</label></span><input id="ad-e" type="email" name="email" maxlength="160"></div>
    <div class="f"><span class="f__k"><label for="ad-p">Téléphone</label></span><input id="ad-p" type="text" name="phone" maxlength="30"></div>
    <div class="f f--full"><span class="f__k"><label for="ad-ad">Adresse</label></span><input id="ad-ad" type="text" name="address" maxlength="160"></div>
    <div class="f"><span class="f__k"><label for="ad-z">Code postal</label></span><input id="ad-z" type="text" name="zip" maxlength="12"></div>
    <div class="f"><span class="f__k"><label for="ad-c">Ville</label></span><input id="ad-c" type="text" name="city" maxlength="80"></div>
    <div class="f"><span class="f__k"><label for="ad-y">Année d’adhésion</label></span><input id="ad-y" type="text" inputmode="numeric" name="year" value="<?= (int) date('Y') ?>" maxlength="4"></div>
    <div class="f"><span class="f__k"><label for="ad-pr">Règlement</label></span><select id="ad-pr" name="provider"><?php foreach (['cheque', 'especes', 'virement', 'helloasso', 'autre'] as $k): ?><option value="<?= e($k) ?>"><?= e(Membership::PROVIDERS[$k]) ?></option><?php endforeach; ?></select></div>
    <div class="f f--full"><span class="f__k"><label for="ad-fa">Autres membres (famille)</label></span><input id="ad-fa" type="text" name="family" maxlength="300"></div>
    <div class="f f--full"><label class="toggle"><input type="checkbox" name="paid" value="1" checked><span class="toggle__box"></span><span>Règlement déjà reçu</span></label></div>
    <div class="f f--full"><label class="toggle"><input type="checkbox" name="welcome" value="1"><span class="toggle__box"></span><span>Envoyer l’e-mail de bienvenue (si l’e-mail est saisi)</span></label></div>
  </div>
  <div class="row"><button type="submit" class="btn btn--navy">Ajouter l’adhésion</button></div>
</form>
