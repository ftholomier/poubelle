<?php
/**
 * Coûts de l'IA (API Gemini). Variables : $today, $month, $total, $due, $budget, $uses, $recent, $at (voir Costs::live),
 * $prices, $custom, $months, $model, $modelPrice, $admin, $payer, $rate, $free
 */
use App\Services\AiCosts;

$num = fn (float $v, int $d = 2) => number_format($v, $d, ',', ' ');
$price = fn (float $v) => rtrim(rtrim(number_format($v, 4, ',', ' '), '0'), ',');
?>
<?php if ($free): ?><p class="alert alert--info small" style="margin:0">Clé Gemini sur le <b>niveau gratuit</b> de Google : rien n’est facturé. Les appels restent comptés à 0 € ; le coût qu’ils auraient eu est indiqué dans le détail CSV.</p><?php endif; ?>
<?php if ($admin && $payer === ''): ?><p class="alert small" style="margin:0">Indiquez qui avance les frais (<a href="/admin/reglages?groupe=couts">Réglages › Coûts IA</a>) : son nom figure sur le relevé mensuel que l’association rembourse.</p><?php endif; ?>

<div class="kpis" data-costs>
  <div class="kpi kpi--yellow"><b data-live="today.eur"><?= e($today['eur']) ?></b><span>aujourd’hui</span><small><span data-live="today.calls"><?= e($today['calls']) ?></span> · <span data-live="today.usd"><?= e($today['usd']) ?></span></small></div>
  <div class="kpi"><b data-live="month.eur"><?= e($month['eur']) ?></b><span>en <?= e($month['label']) ?></span><small><span data-live="month.calls"><?= e($month['calls']) ?></span> · <span data-live="month.tokens"><?= e($month['tokens']) ?></span> jetons</small></div>
  <a class="kpi<?= $due['has'] ? ' kpi--pink' : '' ?>" href="#mois" data-live-due><b data-live="due.eur"><?= e($due['eur']) ?></b><span>à rembourser par l’association</span><small data-live="due.detail"><?= e($due['detail']) ?></small></a>
  <<?= $admin ? 'a href="/admin/reglages?groupe=couts"' : 'div' ?> class="kpi<?= $budget['over'] ? ' kpi--pink' : '' ?>" data-live-budget><b data-live="budget.pct"><?= $budget['set'] ? (int) $budget['pct'] . ' %' : '—' ?></b><span>du budget du mois</span><small data-live="budget.label"><?= e($budget['label']) ?></small><?php if ($budget['set']): ?><span class="bar bar--navy" style="margin-top:6px"><i data-live-bar style="width:<?= min(100, (int) $budget['pct']) ?>%"></i></span><?php endif; ?></<?= $admin ? 'a' : 'div' ?>>
  <div class="kpi"><b data-live="total.eur"><?= e($total['eur']) ?></b><span>depuis le début</span><small data-live="total.calls"><?= e($total['calls']) ?></small></div>
</div>
<?php if ($budget['over']): ?><p class="alert alert--error small" style="margin:0" data-live-over>Budget du mois atteint : <?= AiCosts::paused('correcteur') ? 'les tâches automatiques (traductions, correcteur, index) sont suspendues jusqu’au 1er du mois prochain' : 'les tâches automatiques continuent (Réglages › Coûts IA)' ?><?= AiCosts::paused('assistant') ? ', l’assistant du site aussi' : '' ?>. Les boutons du back-office restent utilisables.</p><?php endif; ?>

<section class="card">
  <div class="card__head">
    <h2 class="card__t">Derniers appels</h2>
    <span class="card__note costs-live" data-live-status><i class="dot" aria-hidden="true"></i> En direct · mis à jour à <span data-live="at"><?= e($at) ?></span></span>
  </div>
  <div class="table" style="border:0">
    <table>
      <thead><tr><th>Heure</th><th>Usage</th><th>Demandé par</th><th>Concerne</th><th title="Jetons envoyés → jetons produits (réponse et réflexion)">Jetons</th><th style="text-align:right">Coût</th></tr></thead>
      <tbody data-live-recent>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td class="xs nowrap"><?= e($r['at']) ?></td>
          <td class="small nowrap" title="Modèle : <?= e($r['model']) ?>"><?= e($r['use']) ?></td>
          <td class="small"><?= e($r['who']) ?></td>
          <td class="small ellipsis costs-ref"><?= $r['refUrl'] ? '<a class="rowlink" href="' . e($r['refUrl']) . '">' . e($r['ref']) . '</a>' : e($r['ref']) ?></td>
          <td class="xs nowrap"><?= e($r['tokens']) ?></td>
          <td class="t-num" style="text-align:right"><?= e($r['eur']) ?><?= $r['flag'] ? ' <span class="pill pill--warn">' . e($r['flag']) . '</span>' : '' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="6" class="muted" style="padding:24px;text-align:center">Aucun appel à Gemini ce mois-ci.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="cols cols--wide">
  <section class="card">
    <div class="card__head"><h2 class="card__t">Ce mois-ci, par usage</h2></div>
    <div class="table" style="border:0">
      <table style="min-width:0">
        <thead><tr><th>Usage</th><th>Appels</th><th>Coût</th><th title="Coût moyen d’un appel">Par appel</th></tr></thead>
        <tbody data-live-uses>
        <?php foreach ($uses as $u): ?>
          <tr><td class="t-strong"><?= e($u['label']) ?></td><td class="t-num"><?= e($u['calls']) ?></td><td class="t-num"><?= e($u['eur']) ?></td><td class="small"><?= e($u['avg']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$uses): ?><tr><td colspan="4" class="muted" style="padding:18px;text-align:center">Rien ce mois-ci.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card">
    <div class="card__head"><h2 class="card__t">Comment c’est calculé</h2></div>
    <div class="card__body small">
      <p style="margin:0">À chaque réponse, Google indique les <b>jetons</b> consommés (un jeton ≈ 4 caractères) : envoyés (question, texte à traduire ou à relire), produits (réponse) et de réflexion du modèle. Le site les multiplie par le tarif du modèle, en dollars, et convertit en euros au taux de <b>1&nbsp;$ = <?= e($num($rate, 4)) ?>&nbsp;€</b><?= $admin ? ' (<a href="/admin/reglages?groupe=couts">Réglages › Coûts IA</a>)' : '' ?>.</p>
      <?php if ($model): ?>
        <p style="margin:0">Modèle utilisé : <b><?= e($model) ?></b>, soit <?= e($price($modelPrice['in'])) ?>&nbsp;$ par million de jetons envoyés et <?= e($price($modelPrice['out'])) ?>&nbsp;$ par million produits.<?php if (!$modelPrice['known']): ?> <span class="ko">Ce modèle n’est pas dans le barème ci-dessous : tarif par défaut appliqué, ajoutez-le.</span><?php endif; ?></p>
      <?php else: ?>
        <p style="margin:0" class="muted">Aucune clé Gemini : aucun frais.</p>
      <?php endif; ?>
      <p style="margin:0">La <b>facture Google fait foi</b> (<a href="https://console.cloud.google.com/billing" target="_blank" rel="noopener">console Google Cloud › Facturation</a>) : quelques centimes d’écart sont possibles (arrondis, taux de change). Les visiteurs du site ne voient jamais ces montants.</p>
    </div>
  </section>
</div>

<section class="card" id="mois">
  <div class="card__head"><h2 class="card__t">Mois par mois · remboursements</h2><span class="card__note">Relevé PDF à signer et détail de chaque appel (CSV) pour l’association</span></div>
  <div class="table" style="border:0">
    <table>
      <thead><tr><th>Mois</th><th>Appels</th><th>Jetons</th><th>Coût</th><th>Remboursement</th><th>Documents</th></tr></thead>
      <tbody>
      <?php foreach ($months as $m): ?>
        <tr>
          <td class="t-strong nowrap"><?= e(ucfirst($m['label'])) ?></td>
          <td class="t-num"><?= e($m['calls']) ?></td>
          <td class="small nowrap"><?= e($m['tokens']) ?></td>
          <td class="nowrap"><b><?= e($m['eur']) ?></b> <span class="xs muted"><?= e($m['usd']) ?></span></td>
          <td>
            <?php if ($m['paid']): ?>
              <span class="pill pill--ok">Remboursé</span> <span class="xs muted"><?= e(date('d/m/Y', strtotime($m['paid']['at']))) ?> · <?= e($num((float) $m['paid']['eur'])) ?> €<?= $m['paid']['note'] !== '' ? ' · ' . e($m['paid']['note']) : '' ?><?= $m['paid']['by'] !== '' ? ' · noté par ' . e($m['paid']['by']) : '' ?></span>
              <?php if ($admin): ?><form method="post" action="/admin/couts-ia/rembourse" style="display:inline" data-confirm="Annuler ce remboursement ?|<?= e(ucfirst($m['label'])) ?> repassera « à rembourser ».|Annuler le remboursement"><?= csrf_field() ?><input type="hidden" name="mois" value="<?= e($m['ym']) ?>"><button type="submit" name="annuler" value="1" class="linkbtn xs">Annuler</button></form><?php endif; ?>
            <?php elseif ($m['current']): ?>
              <span class="pill pill--info">En cours</span> <span class="xs muted">montant provisoire</span>
            <?php elseif ($m['eurRaw'] <= 0): ?>
              <span class="xs muted">rien à rembourser</span>
            <?php elseif ($admin): ?>
              <form method="post" action="/admin/couts-ia/rembourse" class="row costs-pay"><?= csrf_field() ?><input type="hidden" name="mois" value="<?= e($m['ym']) ?>">
                <span class="pill pill--warn">À rembourser</span>
                <input type="text" class="in in--sm" name="montant" inputmode="decimal" value="<?= e($num($m['eurRaw'])) ?>" aria-label="Montant remboursé en euros (<?= e($m['label']) ?>)" style="width:84px"> €
                <input type="text" class="in in--sm" name="note" maxlength="200" placeholder="virement, chèque…" aria-label="Moyen ou référence du remboursement (<?= e($m['label']) ?>)" style="width:150px">
                <button type="submit" class="btn btn--sm">Noter le remboursement</button>
              </form>
            <?php else: ?>
              <span class="pill pill--warn">À rembourser</span>
            <?php endif; ?>
          </td>
          <td class="nowrap"><a class="linkbtn" href="/admin/couts-ia/releve/<?= e($m['ym']) ?>.pdf">Relevé PDF</a> · <a class="linkbtn" href="/admin/couts-ia/detail/<?= e($m['ym']) ?>.csv">CSV</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$months): ?><tr><td colspan="6" class="muted" style="padding:24px;text-align:center">Aucun appel à Gemini pour l’instant.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card" id="tarifs">
  <div class="card__head"><h2 class="card__t">Barème des modèles</h2><span class="card__note"><?= $custom ? 'barème modifié dans le back-office' : 'tarifs publics de Google, octobre 2026' ?></span></div>
  <div class="card__body">
    <p class="small muted" style="margin:0">Dollars par million de jetons. Le site retient la ligne dont le début d’identifiant est le plus long (« gemini-3.1-flash-lite » avant « gemini-3.1 ») et, s’il y en a plusieurs, la plus récente déjà en vigueur. Une hausse annoncée par Google s’ajoute donc à l’avance, avec sa date. Les appels déjà faits gardent leur coût.</p>
    <?php if ($admin): ?>
      <form class="stack" data-json-form data-url="/admin/couts-ia/tarifs" novalidate>
        <div class="table grid-edit">
          <table>
            <thead><tr><th>Modèle (début de l’identifiant)</th><th>Entrée</th><th>Sortie et réflexion</th><th>Entrée en cache</th><th>À partir du</th><th></th></tr></thead>
            <tbody data-repeater="prices">
            <?php foreach ($prices as $p): ?>
              <tr data-item>
                <td><input type="text" data-field="prefix" value="<?= e($p['prefix']) ?>" aria-label="Modèle" spellcheck="false" style="min-width:190px;font-weight:600"></td>
                <td><input type="text" inputmode="decimal" data-field="in" value="<?= e($price((float) $p['in'])) ?>" aria-label="Entrée, dollars par million de jetons" style="width:90px"></td>
                <td><input type="text" inputmode="decimal" data-field="out" value="<?= e($price((float) $p['out'])) ?>" aria-label="Sortie et réflexion, dollars par million de jetons" style="width:90px"></td>
                <td><input type="text" inputmode="decimal" data-field="cached" value="<?= e($price((float) $p['cached'])) ?>" aria-label="Entrée lue dans le cache, dollars par million de jetons" style="width:90px"></td>
                <td><input type="date" data-field="from" value="<?= e($p['from']) ?>" aria-label="En vigueur à partir du (vide : toujours)"></td>
                <td><button type="button" class="iconbtn" data-rep-del title="Supprimer la ligne" aria-label="Supprimer la ligne">✕</button></td>
              </tr>
            <?php endforeach; ?>
            <template><tr data-item>
              <td><input type="text" data-field="prefix" value="" aria-label="Modèle" spellcheck="false" placeholder="gemini-…" style="min-width:190px;font-weight:600"></td>
              <td><input type="text" inputmode="decimal" data-field="in" value="" aria-label="Entrée, dollars par million de jetons" style="width:90px"></td>
              <td><input type="text" inputmode="decimal" data-field="out" value="" aria-label="Sortie et réflexion, dollars par million de jetons" style="width:90px"></td>
              <td><input type="text" inputmode="decimal" data-field="cached" value="" aria-label="Entrée lue dans le cache, dollars par million de jetons" style="width:90px"></td>
              <td><input type="date" data-field="from" value="" aria-label="En vigueur à partir du (vide : toujours)"></td>
              <td><button type="button" class="iconbtn" data-rep-del title="Supprimer la ligne" aria-label="Supprimer la ligne">✕</button></td>
            </tr></template>
            </tbody>
          </table>
        </div>
        <div class="row"><button type="button" class="btn btn--sm" data-price-add>+ Ajouter un modèle</button><span class="grow"></span><span class="small muted" data-saved></span><button type="submit" class="btn btn--navy" data-save>Enregistrer le barème</button></div>
      </form>
      <?php if ($custom): ?><form method="post" action="/admin/couts-ia/tarifs/defaut" data-confirm="Rétablir les tarifs publics de Google ?|Vos modifications du barème seront perdues.|Rétablir"><?= csrf_field() ?><button type="submit" class="linkbtn">Rétablir les tarifs publics de Google</button></form><?php endif; ?>
    <?php else: ?>
      <div class="table">
        <table>
          <thead><tr><th>Modèle</th><th>Entrée</th><th>Sortie et réflexion</th><th>Entrée en cache</th><th>À partir du</th></tr></thead>
          <tbody>
          <?php foreach ($prices as $p): ?>
            <tr><td class="t-strong"><?= e($p['prefix']) ?></td><td><?= e($price((float) $p['in'])) ?> $</td><td><?= e($price((float) $p['out'])) ?> $</td><td><?= e($price((float) $p['cached'])) ?> $</td><td class="small"><?= $p['from'] ? e(date('d/m/Y', strtotime($p['from']))) : '—' ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>
