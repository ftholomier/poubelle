<?php
/** Boutique › Codes promo. Variables : $promos (code => promo), $models, $edit (promo en cours de modification ou null) */
use App\Shop\Orders;
use App\Shop\Promos;

$p = $edit ?? ['code' => '', 'type' => 'percent', 'value' => 10, 'min' => 0, 'from' => '', 'to' => '', 'max' => 0, 'once' => true, 'models' => [], 'active' => true, 'note' => '', 'uses' => []];
$val = $p['type'] === 'percent' ? (string) $p['value'] : number_format($p['value'] / 100, 2, '.', '');
?>
<link rel="stylesheet" href="<?= asset('admin/boutique.css') ?>">
<div class="cols" style="align-items:start">
  <section class="card card--pad">
    <h2 class="card__t">Codes promo</h2>
    <?php if (!$promos): ?><p class="small muted">Aucun code pour l’instant.</p><?php else: ?>
    <table class="shoporders">
      <thead><tr><th>Code</th><th>Remise</th><th>Validité</th><th>Utilisations</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($promos as $x): ?>
        <tr<?= $x['active'] ? '' : ' style="opacity:.55"' ?>>
          <td><b><?= e($x['code']) ?></b><?= $x['note'] !== '' ? '<br><span class="xs muted">' . e($x['note']) . '</span>' : '' ?></td>
          <td><?= e($x['type'] === 'percent' ? '−' . $x['value'] . ' %' : ($x['type'] === 'amount' ? '−' . Orders::money($x['value']) : 'livraison offerte')) ?><?= $x['min'] ? '<br><span class="xs muted">dès ' . e(Orders::money($x['min'])) . '</span>' : '' ?><?= $x['models'] ? '<br><span class="xs muted">' . count($x['models']) . ' modèle(s)</span>' : '' ?></td>
          <td class="small"><?= $x['from'] !== '' ? 'du ' . e(date('d/m/Y', strtotime($x['from']))) . ' ' : '' ?><?= $x['to'] !== '' ? 'au ' . e(date('d/m/Y', strtotime($x['to']))) : ($x['from'] === '' ? 'sans limite' : '') ?><?= $x['active'] ? '' : '<br><b>désactivé</b>' ?></td>
          <td><?= count($x['uses']) ?><?= $x['max'] ? ' / ' . $x['max'] : '' ?><?= $x['once'] ? '<br><span class="xs muted">1 par client</span>' : '' ?></td>
          <td><a class="btn btn--ghost btn--sm" href="/admin/boutique/promos?code=<?= e(rawurlencode($x['code'])) ?>">Modifier</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
  <form method="post" action="/admin/boutique/promos" class="card card--pad">
    <?= csrf_field() ?><input type="hidden" name="old" value="<?= e($p['code']) ?>">
    <h2 class="card__t"><?= $edit ? 'Modifier le code ' . e($p['code']) : 'Nouveau code' ?></h2>
    <div class="fgrid fgrid--2">
      <label class="f"><span class="f__k">Code (lettres, chiffres)</span><input class="in" name="code" value="<?= e($p['code']) ?>" required maxlength="30" style="text-transform:uppercase" placeholder="CENTENAIRE"></label>
      <label class="f"><span class="f__k">Type</span><select class="in" name="type"><?php foreach (Promos::TYPES as $k => $l): ?><option value="<?= $k ?>"<?= $p['type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <label class="f"><span class="f__k">Valeur (% ou €)</span><input class="in" type="number" step="0.01" min="0" name="value" value="<?= e($val) ?>"></label>
      <label class="f"><span class="f__k">Achats minimum (€, 0 : aucun)</span><input class="in" type="number" step="0.01" min="0" name="min" value="<?= e(number_format($p['min'] / 100, 2, '.', '')) ?>"></label>
      <label class="f"><span class="f__k">Valable du</span><input class="in" type="date" name="from" value="<?= e($p['from']) ?>"></label>
      <label class="f"><span class="f__k">au</span><input class="in" type="date" name="to" value="<?= e($p['to']) ?>"></label>
      <label class="f"><span class="f__k">Utilisations maximum (0 : illimité)</span><input class="in" type="number" min="0" name="max" value="<?= (int) $p['max'] ?>"></label>
      <label class="f"><span class="f__k">Note (pour l’équipe)</span><input class="in" name="note" value="<?= e($p['note']) ?>" placeholder="ex. adhérents 2026"></label>
    </div>
    <div class="f"><span class="f__k">Limité à ces modèles (aucun coché : toute la boutique)</span><div class="row" style="gap:8px;flex-wrap:wrap">
      <?php foreach ($models as $mm): ?><label class="toggle"><input type="checkbox" name="models[]" value="<?= e($mm['id']) ?>"<?= in_array($mm['id'], $p['models'], true) ? ' checked' : '' ?>><span class="toggle__box"></span><span><?= e($mm['name']) ?></span></label><?php endforeach; ?>
    </div></div>
    <label class="toggle"><input type="checkbox" name="once" value="1"<?= $p['once'] ? ' checked' : '' ?>><span class="toggle__box"></span><span>Une seule fois par client (même e-mail)</span></label>
    <label class="toggle"><input type="checkbox" name="active" value="1"<?= $p['active'] ? ' checked' : '' ?>><span class="toggle__box"></span><span>Actif</span></label>
    <div class="row" style="justify-content:space-between;margin-top:10px">
      <?php if ($edit): ?><button class="btn btn--ghost btn--sm" name="action" value="delete">Supprimer</button><?php else: ?><span></span><?php endif; ?>
      <button class="btn btn--navy" name="action" value="save">Enregistrer</button>
    </div>
    <?php if ($edit && $p['uses']): ?>
    <h3 class="card__t card__t--sm" style="margin-top:16px">Utilisations</h3>
    <ul class="small"><?php foreach (array_reverse($p['uses']) as $u): ?><li><a href="/admin/boutique/commandes/<?= e($u['order']) ?>"><?= e($u['order']) ?></a> · <?= e(date('d/m/Y', strtotime($u['at']))) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </form>
</div>
