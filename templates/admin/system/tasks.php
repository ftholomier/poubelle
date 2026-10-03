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
?>
<p class="alert <?= $late ? 'alert--error' : 'alert--ok' ?>" style="margin:0">
  <?= $last ? 'Dernier passage de la tâche planifiée : ' . e(Base::ago(date('c', (int) $last))) . '.' : 'La tâche planifiée n’est jamais passée.' ?>
  <?php if ($late): ?> Vérifiez la ligne cron chez l’hébergeur : <code>*/5 * * * * php <?= e(APP_ROOT) ?>/bin/console.php cron</code><?php endif; ?>
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
