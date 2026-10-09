<?php
/** Newsletter « Ce jour-là ». Variables : $history (lettres suivies), $growth (12 semaines), $stat, $state, $next, $subs (clé => abonné), $found, $q, $items */
use App\Admin\Base;
use App\Core\Auth;
use App\Core\Settings;
use App\Front\Site;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$days = ['1' => 'lundi', '2' => 'mardi', '3' => 'mercredi', '4' => 'jeudi', '5' => 'vendredi', '6' => 'samedi', '7' => 'dimanche'];
$enabled = (bool) Settings::get('newsletter.enabled', false);
?>
<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= $fmt($stat['active']) ?></b><span>abonnés confirmés</span><small>double validation par e-mail</small></div>
  <div class="kpi"><b><?= $fmt($stat['pending']) ?></b><span>en attente de confirmation</span><small>lien envoyé, pas encore cliqué</small></div>
  <div class="kpi"><b><?= $fmt($stat['unsubscribed']) ?></b><span>désinscriptions</span><small>adresses effacées</small></div>
  <div class="kpi"><b><?= $enabled ? 'Auto' : 'Off' ?></b><span><?= $enabled ? 'envoi le ' . e($days[(string) Settings::get('newsletter.weekday', 1)] ?? '') . ' à ' . (int) Settings::get('newsletter.hour', 8) . ' h' : 'envoi automatique désactivé' ?></span><small><?= $next ? 'prochain : ' . e(date('d/m à H:i', strtotime($next))) : (Auth::isAdmin() ? '<a href="/admin/reglages?groupe=newsletter">activer dans les réglages</a>' : 'réglable par un administrateur') ?></small></div>
</div>

<?php
$pct = fn ($v) => $v === null ? '–' : number_format($v * 100, 1, ',', ' ') . ' %';
$recent = array_values(array_filter($history, fn ($h) => $h['sent'] > 0));
$avg = function (string $k) use ($recent) {
    $r = array_slice(array_filter(array_column($recent, $k), fn ($v) => $v !== null), 0, 4);
    return $r ? array_sum($r) / count($r) : null;
};
$sending = $state && !empty($state['pending']);
$last = $history[0] ?? null;
$maxG = max(1, ...array_values(array_map(fn ($g) => max($g["in"], $g["out"]), $growth)));
?>
<div class="kpis">
  <div class="kpi"><b><?= $pct($avg('openRate')) ?></b><span>taux d’ouverture</span><small>moyenne des 4 dernières lettres (approximatif)</small></div>
  <div class="kpi"><b><?= $pct($avg('clickRate')) ?></b><span>taux de clic</span><small>abonnés ayant cliqué au moins un lien</small></div>
  <div class="kpi"><b><?= $fmt(array_sum(array_column(array_slice($recent, 0, 4), 'unsub'))) ?></b><span>désinscriptions</span><small>après les 4 dernières lettres</small></div>
  <div class="kpi"><b><?= $fmt(array_sum(array_column($growth, 'in')) - array_sum(array_column($growth, 'out'))) ?></b><span>solde sur 12 semaines</span><small><?= $fmt(array_sum(array_column($growth, 'in'))) ?> arrivées · <?= $fmt(array_sum(array_column($growth, 'out'))) ?> départs</small></div>
</div>

<?php if ($sending):
    $done = (int) ($state['sent'] ?? 0) + (int) ($state['failed'] ?? 0);
    $left = count($state['pending']);
    $total = max(1, $done + $left);
    $eta = (int) ceil($left / 300 * 60); ?>
  <div class="card card--pad nl-live" style="border-color:var(--yellow);border-width:3px">
    <div class="row" style="justify-content:space-between;gap:12px;flex-wrap:wrap">
      <h2 class="card__t" style="margin:0">Envoi en cours · <?= e((string) ($state['week'] ?? '')) ?></h2>
      <span class="pill pill--warn">● en direct, mis à jour toutes les 20 s</span>
    </div>
    <div style="height:18px;background:var(--cream-2,#E8DFC9);border:2px solid var(--navy);margin:14px 0 8px"><div style="height:100%;width:<?= round($done / $total * 100, 1) ?>%;background:var(--yellow)"></div></div>
    <p style="margin:0"><b><?= $fmt($state['sent'] ?? 0) ?></b> envoyé(s) · <b><?= $fmt($left) ?></b> restant(s)<?= !empty($state['failed']) ? ' · <span class="ko">' . $fmt($state['failed']) . ' échec(s)</span>' : '' ?> · <?= round($done / $total * 100) ?> % · fin estimée dans <?= $eta >= 60 ? floor($eta / 60) . ' h ' . sprintf('%02d', $eta % 60) : $eta . ' min' ?></p>
    <p class="small muted" style="margin:4px 0 0">Lots de 10 à 30 e-mails, pauses de 3 à 40 secondes au hasard, 300 e-mails par heure au plus, à chaque passage de la tâche planifiée (toutes les 5 minutes).</p>
  </div>
  <script nonce="<?= e(csp_nonce()) ?>">setTimeout(() => { if (!document.querySelector('form :focus')) location.reload(); }, 20000);</script>
<?php endif; ?>

<?php if ($last): ?>
<div class="cols cols--wide">
  <div class="card">
    <div class="card__head"><h2 class="card__t">Dernière lettre</h2><span class="small muted"><?= e($last['subject']) ?></span></div>
    <div class="card__body">
      <div class="kpis" style="margin:0;grid-template-columns:repeat(2,minmax(0,1fr))">
        <div class="kpi"><b><?= $fmt($last['sent']) ?></b><span>envoyés</span><small><?= $last['failed'] ? $fmt($last['failed']) . ' échec(s)' : 'aucun échec' ?><?= $last['finished'] ? ' · fini ' . e(Base::ago($last['finished'])) : ' · en cours' ?></small></div>
        <div class="kpi"><b><?= $pct($last['openRate']) ?></b><span>ouvertures</span><small><?= $fmt($last['opens']) ?> abonné(s)</small></div>
        <div class="kpi"><b><?= $pct($last['clickRate']) ?></b><span>clics</span><small><?= $fmt($last['clickers']) ?> abonné(s), <?= $fmt($last['clicks']) ?> clic(s)</small></div>
        <div class="kpi"><b><?= $fmt($last['unsub']) ?></b><span>désinscriptions</span><small><?= $last['unsubVia'] ? e(implode(' · ', array_map(fn ($k, $v) => $v . ' ' . $k, array_keys($last['unsubVia']), $last['unsubVia']))) : 'aucune' ?></small></div>
      </div>
      <h3 class="card__t card__t--sm" style="margin:16px 0 6px">Liens les plus cliqués</h3>
      <?php foreach (array_slice($last['top'], 0, 7) as $t): ?>
        <div class="row small" style="gap:10px;align-items:center;margin:4px 0">
          <span style="flex:0 0 120px;height:10px;background:var(--cream-2,#E8DFC9)"><span style="display:block;height:100%;width:<?= round($t['people'] / max(1, $last['top'][0]['people']) * 100) ?>%;background:var(--blue)"></span></span>
          <b style="min-width:3ch"><?= $fmt($t['people']) ?></b>
          <a href="<?= e($t['url']) ?>" target="_blank" rel="noopener" style="flex:1;min-width:0;overflow-wrap:anywhere"><?= e($t['label']) ?></a>
        </div>
      <?php endforeach; ?>
      <?php if (!$last['top']): ?><p class="small muted" style="margin:0">Pas encore de clic.</p><?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card__head"><h2 class="card__t">Abonnés, 12 semaines</h2><span class="small muted"><span style="color:var(--blue)">■</span> arrivées · <span style="color:#C0392B">■</span> départs</span></div>
    <div class="card__body">
      <div style="display:flex;align-items:flex-end;gap:6px;height:140px;border-bottom:2px solid var(--navy)">
        <?php foreach ($growth as $g): ?>
          <div style="flex:1;display:flex;gap:2px;align-items:flex-end;height:100%" title="Semaine du <?= e($g['label']) ?> : <?= $g['in'] ?> arrivée(s), <?= $g['out'] ?> départ(s)">
            <span style="flex:1;background:var(--blue);height:<?= round($g['in'] / $maxG * 100) ?>%;min-height:<?= $g['in'] ? 3 : 0 ?>px"></span>
            <span style="flex:1;background:#C0392B;height:<?= round($g['out'] / $maxG * 100) ?>%;min-height:<?= $g['out'] ? 3 : 0 ?>px"></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:6px" class="xs muted"><?php foreach ($growth as $g): ?><span style="flex:1;text-align:center"><?= e($g['label']) ?></span><?php endforeach; ?></div>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:18px">
  <div class="card__head"><h2 class="card__t">Historique des lettres</h2></div>
  <div class="table"><table>
    <thead><tr><th>Semaine</th><th>Partie</th><th class="t-num">Envoyés</th><th class="t-num">Échecs</th><th class="t-num">Ouvertures</th><th class="t-num">Clics</th><th class="t-num">Désinscriptions</th><th>Lien le plus cliqué</th></tr></thead>
    <tbody>
    <?php foreach ($history as $h): ?>
      <tr>
        <td><b><?= e($h['week']) ?></b></td>
        <td class="small"><?= $h['started'] ? e(date('d/m/Y H:i', strtotime((string) $h['started']))) : '–' ?><?= $h['finished'] ? '' : ' <span class="pill pill--warn">en cours</span>' ?></td>
        <td class="t-num"><?= $fmt($h['sent']) ?><?= $h['total'] > $h['sent'] + $h['failed'] ? ' / ' . $fmt($h['total']) : '' ?></td>
        <td class="t-num"><?= $h['failed'] ? '<span class="ko">' . $fmt($h['failed']) . '</span>' : '0' ?></td>
        <td class="t-num"><?= $pct($h['openRate']) ?></td>
        <td class="t-num"><?= $pct($h['clickRate']) ?></td>
        <td class="t-num"><?= $fmt($h['unsub']) ?></td>
        <td class="small"><?= $h['top'] ? e($h['top'][0]['label']) . ' (' . $h['top'][0]['people'] . ')' : '–' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="card__foot xs muted">Ouvertures approximatives : certaines messageries chargent les images d’office (Apple Mail), d’autres les bloquent ; un clic compte aussi comme ouverture. Les clics des antivirus de messagerie peuvent gonfler un peu les chiffres.</div>
</div>
<?php endif; ?>

<div class="cols cols--wide">
  <div class="stack">
    <div class="card">
      <div class="card__head"><h2 class="card__t">Prochaine lettre</h2><a class="linkbtn" href="/admin/newsletter/apercu" target="_blank" rel="noopener">Aperçu ↗</a></div>
      <div class="card__body">
        <p style="margin:0"><b>Objet :</b> <?= e(\App\Services\Newsletter::subject()) ?></p>
        <p class="small muted" style="margin:0">Les matchs de la semaine dans l’histoire du club (<?= count($items) ?> cette semaine), le compte à rebours du centenaire (J-<?= (int) Site::daysToCentenary() ?>) et un rappel aux dons. Chaque abonné la reçoit dans sa langue (FR ou EN).</p>
        <?php foreach ($items as $m): ?>
          <div class="row small" style="gap:8px"><span class="pill"><?= e(date_num((string) $m['date'])) ?></span><a href="<?= e($m['path']) ?>" target="_blank" rel="noopener"><?= e(Site::matchLabel($m)) ?></a></div>
        <?php endforeach; ?>
        <?php if (!$items): ?><p class="small muted" style="margin:0">Aucun match fiché cette semaine dans l’histoire : la lettre invite à explorer les saisons.</p><?php endif; ?>
      </div>
      <div class="card__foot">
        <form method="post" action="/admin/newsletter"><?= csrf_field() ?><button type="submit" name="action" value="test" class="btn">M’envoyer un test</button></form>
        <form method="post" action="/admin/newsletter" data-confirm="Envoyer maintenant ?|La lettre part tout de suite à <?= $fmt($stat['active']) ?> abonné(s). L’envoi automatique de cette semaine sera considéré comme fait.|Envoyer"><?= csrf_field() ?><button type="submit" name="action" value="envoyer" class="btn btn--navy"<?= $stat['active'] ? '' : ' disabled' ?>>Envoyer maintenant</button></form>
        <?php if (Auth::isAdmin()): ?><a class="btn btn--ghost" href="/admin/reglages?groupe=newsletter">Objet, texte d’introduction, jour d’envoi…</a><?php endif; ?>
      </div>
    </div>
    <div class="card card--pad">
      <h2 class="card__t card__t--sm">Ajouter un abonné</h2>
      <p class="small muted" style="margin:0 0 10px">Pour une personne qui vous a demandé de l’inscrire : elle est abonnée tout de suite, sans e-mail de confirmation. Chaque lettre garde son lien de désinscription.</p>
      <form method="post" action="/admin/newsletter" class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-end"><?= csrf_field() ?>
        <label class="f" style="flex:1 1 240px;margin:0"><span class="f__k">Adresse e-mail</span><input type="email" name="email" required maxlength="190" placeholder="prenom.nom@exemple.fr"></label>
        <label class="f" style="margin:0"><span class="f__k">Langue</span><select name="lang"><option value="fr">Français</option><option value="en">Anglais</option></select></label>
        <button type="submit" name="action" value="ajouter" class="btn btn--navy">Abonner</button>
      </form>
    </div>
    <?php if ($state): ?>
      <div class="card card--pad">
        <h2 class="card__t card__t--sm">Dernier envoi</h2>
        <p style="margin:0">Semaine <?= e((string) ($state['week'] ?? '')) ?> · commencé <?= e(Base::ago($state['started'] ?? null)) ?><?= !empty($state['finished']) ? ', terminé ' . e(Base::ago($state['finished'])) : '' ?></p>
        <p style="margin:0"><span class="ok"><?= $fmt($state['sent'] ?? 0) ?> envoyé(s)</span><?= !empty($state['failed']) ? ' · <span class="ko">' . $fmt($state['failed']) . ' échec(s)</span>' : '' ?><?= !empty($state['pending']) ? ' · ' . $fmt(count($state['pending'])) . ' en attente (envoi par lots de 10 à 30, pauses au hasard, 300 par heure au plus)' : '' ?></p>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__t">Abonnés</h2>
      <form class="toolbar" method="get" action="/admin/newsletter"><div class="search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher une adresse…" aria-label="Rechercher un abonné"><button type="submit" aria-label="Rechercher">→</button></div></form>
    </div>
    <?php foreach ($subs as $key => $s): ?>
      <div class="card__row" style="grid-template-columns:minmax(0,1fr) auto auto">
        <span style="overflow-wrap:anywhere"><?= e((string) $s['email']) ?><br><span class="xs muted"><?= ($s['status'] ?? '') === 'active' ? 'confirmé ' . e(Base::ago($s['confirmed'] ?? $s['created'] ?? null)) : 'en attente depuis ' . e(Base::ago($s['requested'] ?? $s['created'] ?? null)) ?> · <?= e(strtoupper($s['lang'] ?? 'fr')) ?></span></span>
        <span class="pill pill--<?= ($s['status'] ?? '') === 'active' ? 'ok' : 'warn' ?>"><?= ($s['status'] ?? '') === 'active' ? 'Actif' : 'À confirmer' ?></span>
        <form method="post" action="/admin/newsletter" data-confirm="Désinscrire cette adresse ?|L’adresse sera effacée de la liste.|Désinscrire|danger"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e((string) $key) ?>"><button type="submit" name="action" value="desinscrire" class="iconbtn" title="Désinscrire" aria-label="Désinscrire">✕</button></form>
      </div>
    <?php endforeach; ?>
    <?php if (!$subs): ?><div class="card__body muted">Aucun abonné<?= $q !== '' ? ' pour cette recherche' : ' pour le moment' ?>.</div><?php endif; ?>
    <?php if ($found > count($subs)): ?><div class="card__foot small muted"><?= $fmt($found - count($subs)) ?> autres : affinez la recherche.</div><?php endif; ?>
  </div>
</div>
