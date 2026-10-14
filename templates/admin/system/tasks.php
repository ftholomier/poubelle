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
<div class="card" id="serveur">
  <div class="card__head"><h2 class="card__t">Serveur</h2><span class="card__note">PHP <?= e(PHP_VERSION) ?> (<?= e(PHP_SAPI) ?><?= $op ? ', OPcache ' . $op['used'] . ' Mo sur ' . $op['total'] . ($op['wasted'] ? ' (dont ' . $op['wasted'] . ' Mo de copies périmées, récupérés au prochain nettoyage)' : '') . ', ' . $op['scripts'] . ' fichiers, ' . $op['hits'] . ' % trouvés en mémoire' : (extension_loaded('Zend OPcache') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN) ? ', OPcache' : '') ?>) · mémoire <?= e((string) ini_get('memory_limit')) ?> · durée maximale d’une page <?= e((string) ini_get('max_execution_time')) ?> s</span></div>
  <div class="card--pad stack" style="gap:10px">
    <?php if (Auth::isAdmin() && $op): ?>
      <?php $og = \App\Core\OpcacheGuard::state(); $lr = preg_match('#^(/home\d*/[^/]+)/#', APP_ROOT . '/', $hm) ? @filemtime($hm[1] . '/.lsphp_restart.txt') : false; ?>
      <p class="xs muted" style="margin:0;font-family:monospace">Diagnostic OPcache : utile <?= $op['useful'] ?> Mo · périmé <?= $op['wasted'] ?> Mo · libre <?= $op['free'] ?> Mo · <?= $op['scripts'] ?> fichiers · démarré <?= $op['start'] ? e(date('d/m H:i:s', $op['start'])) : '?' ?> · dernier vidage <?= $op['restart'] ? e(date('d/m H:i:s', $op['restart'])) : 'jamais' ?> · vidages manuels <?= $op['manual'] ?> · vidage en attente <?= $op['pending'] ? 'OUI' : 'non' ?> · restrict_api « <?= e($op['restrict']) ?> » · processus <?= (int) $op['pid'] ?> · redémarrage LiteSpeed demandé <?= $lr ? e(date('d/m H:i:s', $lr)) : 'jamais' ?> · opcache_reset <?= function_exists('opcache_reset') ? 'disponible' : 'DÉSACTIVÉE par l’hébergeur' ?> · dossier du compte <?= !empty($hm[1]) ? (@is_writable($hm[1]) ? 'accessible' : 'INACCESSIBLE') : '?' ?> · open_basedir « <?= e((string) ini_get('open_basedir')) ?> » · max_wasted <?= e((string) ini_get('opcache.max_wasted_percentage')) ?> %</p>
      <p class="small muted" style="margin:0">Nettoyage automatique d’OPcache : dès que les copies périmées dépassent le quart de sa mémoire (vérifié toutes les 5 min). <?= $og['count'] ? $og['count'] . ' nettoyage(s), le dernier ' . e(\App\Admin\Base::ago((string) $og['last'])) . ' (' . $og['freed'] . ' Mo récupérés).' : 'Aucun pour l’instant.' ?></p>
      <form method="post" action="/admin/taches" class="row" data-confirm="Vider la mémoire OPcache ?|Le code sera recompilé : les premières pages seront un peu plus lentes pendant quelques secondes.|Vider"><?= csrf_field() ?><input type="hidden" name="task" value="opcache-reset"><button type="submit" class="btn btn--sm">Vider OPcache</button><span class="small muted">À utiliser si la mémoire OPcache est presque pleine et que le site ralentit.</span></form>
    <?php endif; ?>
    <?php if (Auth::isAdmin()): $rd = \App\Core\PhpCache::redis(); $rinfo = null; if ($rd) { try { $rinfo = $rd->info('memory'); $rkeys = count($rd->keys('*') ?: []); } catch (\Throwable) {} } ?>
      <p class="small" style="margin:0"><b>Redis</b> (mémoire partagée pour les gros caches, tous musées) :
        <?php if ($rd): ?><span class="ok">✓ connecté</span> (<?= e((string) \App\Core\PhpCache::$redisWhere) ?><?= $rinfo ? ', ' . e((string) ($rinfo['used_memory_human'] ?? '?')) . ' utilisés, ' . (int) $rkeys . ' entrées du musée' : '' ?>)
        <?php elseif (!class_exists('Redis')): ?><span class="muted">extension PHP « redis » absente : les caches restent sur disque (cPanel › Sélectionner une version de PHP › Extensions).</span>
        <?php else: ?><span class="muted">non connecté<?= \App\Core\PhpCache::$redisError ? ' (' . e(\App\Core\PhpCache::$redisError) . ')' : '' ?> : les caches restent sur disque. Installez Redis (cPanel › Redis Manager, mode Socket) ; s’il est actif, collez son mot de passe ci-dessous.</span><?php endif; ?></p>
      <form method="post" action="/admin/taches" class="row"><?= csrf_field() ?><input type="hidden" name="task" value="redis-save"><input type="password" name="password" class="in" placeholder="Mot de passe Redis (cPanel › Redis Manager)" autocomplete="off" style="max-width:340px"><button type="submit" class="btn btn--sm">Enregistrer et tester</button></form>
      <?php if ($rd): ?><form method="post" action="/admin/taches" class="row" data-confirm="Vider le cache Redis ?|Il se remplit de nouveau tout seul au fil des pages.|Vider"><?= csrf_field() ?><input type="hidden" name="task" value="redis-flush"><button type="submit" class="btn btn--sm">Vider le cache Redis</button></form><?php endif; ?>
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
