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
<?php
// Hébergement : ce que le site exige (PHP 8.3, extensions, dossiers inscriptibles) et dernières erreurs.
$srv = \App\Services\ServerCheck::problems();
$errs = \App\Services\ServerCheck::lastErrors(10);
$op = \App\Services\ServerCheck::opcache();
?>
<div class="card" id="serveur">
  <div class="card__head"><h2 class="card__t">Serveur</h2><span class="card__note">PHP <?= e(PHP_VERSION) ?> (<?= e(PHP_SAPI) ?><?= $op ? ', OPcache ' . $op['used'] . ' Mo sur ' . $op['total'] . ', ' . $op['scripts'] . ' fichiers, ' . $op['hits'] . ' % trouvés en mémoire' : (extension_loaded('Zend OPcache') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN) ? ', OPcache' : '') ?>) · mémoire <?= e((string) ini_get('memory_limit')) ?> · durée maximale d’une page <?= e((string) ini_get('max_execution_time')) ?> s</span></div>
  <div class="card--pad stack" style="gap:10px">
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
