<?php
/** Tâches planifiées. Variables : $state, $tasks, $last */
use App\Admin\Base;
use App\Core\Auth;

$every = fn (int $s) => match (true) {
    $s === 0 => 'à chaque passage (5 min)',
    $s < 3600 => 'toutes les ' . intdiv($s, 60) . ' min',
    $s < 86400 => 'toutes les heures',
    default => 'une fois par jour',
};
$late = !$last || $last < time() - 20 * 60;
// PHP « ligne de commande » du serveur, à côté de celui du site (o2switch : …/usr/bin/lsphp → …/usr/bin/php).
$cli = PHP_SAPI === 'cli' ? PHP_BINARY : (is_file($c = dirname(PHP_BINARY) . '/php') ? $c : 'php');
?>
<?php
// Hébergement : ce que le site exige (PHP 8.3, extensions, dossiers inscriptibles) et dernières erreurs.
$srv = \App\Services\ServerCheck::problems();
$errs = \App\Services\ServerCheck::lastErrors(10);
$op = \App\Services\ServerCheck::opcache();
?>
<?php
$rdi = Auth::isAdmin() ? \App\Services\ServerCheck::redis() : null;
$og = \App\Core\OpcacheGuard::state();
$lr = preg_match('#^(/home\d*/[^/]+)/#', APP_ROOT . '/', $hm) ? @filemtime($hm[1] . '/.lsphp_restart.txt') : false;
// Jauge : segments [part (0-1), couleur, libellé] ; couleur de l'ensemble selon le remplissage.
$gauge = function (array $segs, float $fill): string {
    $h = '<div class="srvg__bar" role="img" aria-label="' . e(implode(', ', array_map(fn ($x) => $x[2], $segs))) . '">';
    foreach ($segs as [$part, $color]) {
        $h .= '<span style="width:' . max(0, min(100, round($part * 100, 1))) . '%;background:' . $color . '"></span>';
    }
    return $h . '</div>';
};
$level = fn (float $r) => $r >= 0.9 ? ['ko', 'Presque plein'] : ($r >= 0.7 ? ['warn', 'À surveiller'] : ['ok', 'Normal']);
?>
<style>
.srv__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 14px; }
.srvg { border: 2px solid var(--navy); background: var(--paper); padding: 14px 16px; display: flex; flex-direction: column; gap: 8px; }
.srvg__t { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; margin: 0; font-family: var(--display); font-size: 20px; text-transform: uppercase; }
.srvg__big { font-family: var(--display); font-size: 34px; line-height: 1; }
.srvg__big small { font-size: 16px; color: var(--muted); }
.srvg__bar { display: flex; height: 16px; background: var(--sand); border: 1px solid var(--navy); overflow: hidden; }
.srvg__bar span { display: block; height: 100%; }
.srvg__leg { display: flex; flex-wrap: wrap; gap: 4px 14px; margin: 0; padding: 0; list-style: none; font-size: 14px; }
.srvg__leg i { display: inline-block; width: 10px; height: 10px; margin-right: 5px; border: 1px solid var(--navy); vertical-align: -1px; }
.srvg__pill { font: 600 13px var(--display); letter-spacing: .04em; text-transform: uppercase; padding: 2px 8px; border: 1px solid currentColor; }
.srvg__pill.ok { color: var(--okd); } .srvg__pill.warn { color: var(--warn); } .srvg__pill.ko { color: var(--red); }
.srvg form { margin: 0; }
.srv__info { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 8px; margin: 0; }
.srv__info div { background: var(--paper); border: 1px solid var(--mist); padding: 8px 10px; }
.srv__info dt { font-size: 13px; color: var(--muted); } .srv__info dd { margin: 0; font-family: var(--display); font-size: 18px; }
</style>
<div class="card" id="serveur">
  <div class="card__head"><h2 class="card__t">Serveur</h2><span class="card__note">Mémoire du site et réglages de PHP<?= Auth::isAdmin() ? ' · alerte par e-mail aux administrateurs à 90 %' : '' ?></span></div>
  <div class="card--pad stack" style="gap:14px">
    <dl class="srv__info">
      <div><dt>PHP</dt><dd><?= e(PHP_VERSION) ?> <small class="muted">(<?= e(PHP_SAPI) ?>)</small></dd></div>
      <div><dt>Mémoire d’une page</dt><dd><?= e((string) ini_get('memory_limit')) ?></dd></div>
      <div><dt>Durée maximale d’une page</dt><dd><?= e((string) ini_get('max_execution_time')) ?> s</dd></div>
      <?php if ($op): ?><div><dt>Trouvé en mémoire (OPcache)</dt><dd><?= e((string) $op['hits']) ?> %</dd></div><?php endif; ?>
    </dl>
    <?php if (Auth::isAdmin()): ?>
    <div class="srv__grid">
      <?php if ($op): $r = $op['total'] ? $op['used'] / $op['total'] : 0; [$cls, $lab] = $level($r); ?>
      <section class="srvg" aria-labelledby="srvg-op">
        <h3 class="srvg__t" id="srvg-op">OPcache <span class="srvg__pill <?= $cls ?>"><?= $lab ?></span></h3>
        <p class="small muted" style="margin:0">Le code et les données lues à chaque page, gardés compilés en mémoire.</p>
        <div class="srvg__big"><?= $op['used'] ?> <small>Mo sur <?= $op['total'] ?> · <?= round($r * 100) ?> %</small></div>
        <?= $gauge([[$op['total'] ? $op['useful'] / $op['total'] : 0, 'var(--navy)', 'utile ' . $op['useful'] . ' Mo'], [$op['total'] ? $op['wasted'] / $op['total'] : 0, 'var(--yellow)', 'copies périmées ' . $op['wasted'] . ' Mo']], $r) ?>
        <ul class="srvg__leg">
          <li><i style="background:var(--navy)"></i>Utile <?= $op['useful'] ?> Mo</li>
          <li><i style="background:var(--yellow)"></i>Copies périmées <?= $op['wasted'] ?> Mo</li>
          <li><i style="background:var(--sand)"></i>Libre <?= $op['free'] ?> Mo</li>
          <li><?= $op['scripts'] ?> fichiers</li>
        </ul>
        <p class="xs muted" style="margin:0">Nettoyage automatique dès 25 % de copies périmées : <?= $og['count'] ? $og['count'] . ' fois, le dernier ' . e(\App\Admin\Base::ago((string) $og['last'])) . ' (' . $og['freed'] . ' Mo récupérés)' : 'aucun pour l’instant' ?>.</p>
        <form method="post" action="/admin/taches" data-confirm="Vider la mémoire OPcache ?|Le code sera recompilé : les premières pages seront un peu plus lentes pendant quelques secondes.|Vider"><?= csrf_field() ?><input type="hidden" name="task" value="opcache-reset"><button type="submit" class="btn btn--sm">Vider OPcache</button></form>
      </section>
      <?php endif; ?>
      <section class="srvg" aria-labelledby="srvg-rd">
        <?php if ($rdi): $r = $rdi['max'] ? $rdi['used'] / $rdi['max'] : 0; [$cls, $lab] = $level($r); ?>
        <h3 class="srvg__t" id="srvg-rd">Redis <span class="srvg__pill <?= $cls ?>"><?= $lab ?></span></h3>
        <p class="small muted" style="margin:0">Les autres caches (médiathèque, albums, recherche…), partagés par tous les musées.</p>
        <div class="srvg__big"><?= e((string) $rdi['used']) ?> <small>Mo sur <?= e((string) $rdi['max']) ?> · <?= round($r * 100) ?> %</small></div>
        <?= $gauge([[$r, 'var(--blue)', 'utilisé ' . $rdi['used'] . ' Mo']], $r) ?>
        <ul class="srvg__leg">
          <li><i style="background:var(--blue)"></i>Utilisé <?= e((string) $rdi['used']) ?> Mo</li>
          <li><i style="background:var(--sand)"></i>Libre <?= e((string) max(0, round($rdi['max'] - $rdi['used'], 1))) ?> Mo</li>
          <li><?= (int) $rdi['keys'] ?> entrées du musée</li>
        </ul>
        <p class="xs muted" style="margin:0">Connecté par <?= e($rdi['where']) ?>.</p>
        <form method="post" action="/admin/taches" data-confirm="Vider le cache Redis ?|Il se remplit de nouveau tout seul au fil des pages.|Vider"><?= csrf_field() ?><input type="hidden" name="task" value="redis-flush"><button type="submit" class="btn btn--sm">Vider le cache Redis</button></form>
        <?php else: ?>
        <h3 class="srvg__t" id="srvg-rd">Redis <span class="srvg__pill warn">Non connecté</span></h3>
        <p class="small muted" style="margin:0"><?= !class_exists('Redis') ? 'Extension PHP « redis » absente : les caches restent sur disque (cPanel › Sélectionner une version de PHP › Extensions).' : 'Les caches restent sur disque' . (\App\Core\PhpCache::$redisError ? ' (' . e(\App\Core\PhpCache::$redisError) . ')' : '') . '. Installez Redis (cPanel › Redis Manager, mode Socket) ; s’il est actif, collez son mot de passe ci-dessous.' ?></p>
        <?php endif; ?>
        <details><summary class="small">Mot de passe Redis</summary>
          <form method="post" action="/admin/taches" class="row" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="task" value="redis-save"><input type="password" name="password" class="in" placeholder="cPanel › Redis Manager" autocomplete="off" style="max-width:260px"><button type="submit" class="btn btn--sm">Enregistrer et tester</button></form>
        </details>
      </section>
    </div>
    <?php if ($op): ?>
    <details><summary class="small">Diagnostic technique d’OPcache</summary>
      <p class="xs muted" style="margin:8px 0 0;font-family:monospace">démarré <?= $op['start'] ? e(date('d/m H:i:s', $op['start'])) : '?' ?> · dernier vidage <?= $op['restart'] ? e(date('d/m H:i:s', $op['restart'])) : 'jamais' ?> · vidages manuels <?= $op['manual'] ?> · vidage en attente <?= $op['pending'] ? 'OUI' : 'non' ?> · redémarrage LiteSpeed demandé <?= $lr ? e(date('d/m H:i:s', $lr)) : 'jamais' ?> · opcache_reset <?= function_exists('opcache_reset') ? 'disponible' : 'DÉSACTIVÉE' ?> · restrict_api « <?= e($op['restrict']) ?> » · open_basedir « <?= e((string) ini_get('open_basedir')) ?> » · max_wasted <?= e((string) ini_get('opcache.max_wasted_percentage')) ?> % · processus <?= (int) $op['pid'] ?></p>
    </details>
    <?php $byDir = \App\Services\ServerCheck::opcacheByDir(); if ($byDir): ?>
    <details><summary class="small">Ce qui occupe OPcache (par dossier, tous musées confondus)</summary>
      <table class="xs" style="margin:8px 0 0;font-family:monospace;border-collapse:collapse"><?php foreach ($byDir as $d): ?><tr><td style="padding:1px 14px 1px 0"><?= e($d['dir']) ?></td><td style="text-align:right;padding-right:14px"><?= e(number_format($d['mb'], 1, ',', ' ')) ?> Mo</td><td class="muted"><?= (int) $d['n'] ?> fichiers</td></tr><?php endforeach; ?></table>
    </details>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($srv): ?>
      <ul class="small ko" style="margin:0;padding-left:18px"><?php foreach ($srv as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
    <?php else: ?>
      <p class="small ok" style="margin:0">✓ Version de PHP, extensions et droits des dossiers conformes.</p>
    <?php endif; ?>
    <details>
      <summary class="small">Dernières erreurs du journal PHP (<?= count($errs) ?>)</summary>
      <?php if ($errs): ?><ol class="xs" style="margin:8px 0 0;padding-left:18px;font-family:monospace;word-break:break-word"><?php foreach ($errs as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ol>
      <?php else: ?><p class="xs muted" style="margin:8px 0 0">Aucune erreur enregistrée.</p><?php endif; ?>
    </details>
  </div>
</div>
<p class="alert <?= $late ? 'alert--error' : 'alert--ok' ?>" style="margin:0">
  <?= $last ? 'Dernier passage de la tâche planifiée : ' . e(Base::ago(date('c', (int) $last))) . '.' : 'La tâche planifiée n’est jamais passée.' ?>
  <?php if ($late): ?> Vérifiez la ligne cron chez l’hébergeur (toutes les 5 minutes) : <code><?= e($cli) ?> <?= e(APP_ROOT) ?>/bin/console.php cron &gt;/dev/null 2&gt;&amp;1</code><?php endif; ?>
</p>
<div class="table">
  <table>
    <thead><tr><th>Tâche</th><th>Fréquence</th><th>Dernière exécution</th><th>Résultat</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tasks as $k => [$s, $label]): $st = $state[$k] ?? null; ?>
      <tr>
        <td class="t-strong"><?= e($label) ?></td>
        <td class="small"><?= e($every((int) $s)) ?></td>
        <td class="small nowrap"><?= $st ? e(Base::ago(date('c', (int) $st['at']))) : '<span class="muted">jamais</span>' ?></td>
        <td class="small"><?php if ($st): ?><?= $st['ok'] ? '<span class="ok">✓</span>' : '<span class="ko">✕</span>' ?> <?= e(is_string($st['result']) ? $st['result'] : json_encode($st['result'], JSON_UNESCAPED_UNICODE)) ?> <span class="xs muted">(<?= number_format($st['ms'] / 1000, 1, ',', ' ') ?> s)</span><?php endif; ?></td>
        <td><?php if (Auth::isAdmin()): ?><form method="post" action="/admin/taches"><?= csrf_field() ?><input type="hidden" name="task" value="<?= e($k) ?>"><button type="submit" class="btn btn--sm">Lancer</button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if (\App\Services\ReferenceTotals::enabled()):
    // Totaux de référence des joueurs : relevé quotidien des classements publiés (musées qui en déclarent).
    $rt = \App\Services\ReferenceTotals::state();
    $rtSrc = \App\Services\ReferenceTotals::sources();
    $rtData = \App\Services\ReferenceTotals::stored();
    $rtMiss = (array) ($rt['non_rapproches'] ?? []);
?>
<div class="card" id="totaux-reference">
  <div class="card__head"><h2 class="card__t">Totaux de référence des joueurs</h2><span class="card__note"><?= e((string) ($rtSrc['label'] ?? '')) ?> · <a href="<?= e((string) $rtSrc['buteurs']) ?>" target="_blank" rel="noopener">buteurs</a> · <a href="<?= e((string) $rtSrc['matchs']) ?>" target="_blank" rel="noopener">matchs</a></span></div>
  <div class="card--pad stack" style="gap:10px">
    <?php if (!$rt): ?>
      <p class="small muted" style="margin:0">Jamais relevés : les records et « Les chiffres » utilisent les totaux livrés avec le site<?= ($d = \App\Data\Totals::source()['date'] ?? null) ? ' (relevé du ' . e(date_fr((string) $d)) . ')' : '' ?>.</p>
    <?php elseif (!empty($rt['ok'])): ?>
      <p class="small ok" style="margin:0">✓ Dernier relevé <?= e(Base::ago(date('c', (int) $rt['at']))) ?> : <?= (int) $rt['joueurs'] ?> joueurs rapprochés de leur fiche.</p>
    <?php else: ?>
      <p class="alert alert--error" style="margin:0">Dernier relevé <?= e(Base::ago(date('c', (int) $rt['at']))) ?> : <?= e((string) $rt['message']) ?>. Rien n’a été modifié : le site garde les totaux précédents<?= $rtData ? ' (relevé du ' . e(date_fr((string) $rtData['source']['date'])) . ', ' . count($rtData['joueurs']) . ' joueurs)' : '' ?>.</p>
    <?php endif; ?>
    <?php if ($rtMiss): ?>
      <details>
        <summary class="small">Noms non rapprochés d’une fiche (<?= count($rtMiss) ?>) : leurs chiffres restent ceux des matchs racontés</summary>
        <ul class="xs" style="margin:8px 0 0;padding-left:18px"><?php foreach ($rtMiss as $n): ?><li><?= e((string) $n) ?></li><?php endforeach; ?></ul>
        <p class="xs muted" style="margin:8px 0 0">Pour les rapprocher : config/clubs/<?= e(\App\Core\Club::id()) ?>/totaux-alias.php (nom du classement =&gt; titre ou adresse de la fiche).</p>
      </details>
    <?php endif; ?>
    <?php if (Auth::isAdmin()): ?><form method="post" action="/admin/taches"><?= csrf_field() ?><input type="hidden" name="task" value="totaux-reference"><button type="submit" class="btn btn--sm">Relever maintenant</button></form><?php endif; ?>
  </div>
</div>
<?php endif; ?>
