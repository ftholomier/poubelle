<?php
use App\Core\Logger;
use App\Core\Url;
/** @var string $channel @var array $rows @var ?string $date @var string $q @var array $dates */
$labels = ['app' => 'Application', 'error' => 'Erreurs', 'security' => 'Sécurité', 'audit' => 'Actions des administrateurs', 'mail' => 'Emails', 'ai' => 'IA', 'spam' => 'Anti-spam', 'cron' => 'Tâches planifiées', 'import' => 'Migration', 'push' => 'Notifications push'];
?>
<div class="adm-head"><div><h1>Journal</h1><p>Historique technique et traçabilité (conservé 180 jours, données sensibles masquées).</p></div>
  <form method="post" action="<?= e(Url::admin('maintenance/action')) ?>" data-confirm="Supprimer les journaux de plus de 30 jours ?"><?= csrf_field() ?><input type="hidden" name="action" value="logs-prune"><button class="btn btn-sm" type="submit">Purger (> 30 jours)</button></form></div>
<div class="tabs"><?php foreach (Logger::CHANNELS as $c): ?><a href="?canal=<?= e($c) ?>"<?= $channel === $c ? ' class="on"' : '' ?>><?= e($labels[$c] ?? $c) ?></a><?php endforeach; ?></div>
<form class="filters" method="get"><input type="hidden" name="canal" value="<?= e($channel) ?>">
  <div class="field grow"><label for="lq">Rechercher</label><input id="lq" type="search" name="q" value="<?= e($q) ?>"></div>
  <div class="field"><label for="ld">Jour</label><select id="ld" name="date"><option value="">Tous (récents)</option><?php foreach ($dates as $d): ?><option value="<?= e($d) ?>"<?= $date === $d ? ' selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?></select></div>
  <button class="btn btn-ink btn-sm" type="submit">Filtrer</button></form>
<div class="table-wrap">
  <?php foreach ($rows as $r): ?>
    <div class="log-row"><span class="small muted"><?= e(date_fr((string) ($r['t'] ?? ''), 'datetime')) ?></span><span class="lvl <?= e((string) ($r['level'] ?? '')) ?>"><?= e((string) ($r['level'] ?? '')) ?></span>
      <div><strong class="small"><?= e((string) ($r['msg'] ?? '')) ?></strong><?php if (!empty($r['ctx']) || !empty($r['url'])): ?><details><summary><?= e(trim((string) ($r['url'] ?? '') . ' ' . (string) ($r['ip'] ?? ''))) ?: 'détails' ?></summary><pre><?= e((string) json_encode($r['ctx'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></details><?php endif; ?></div></div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><p class="empty-sm" style="margin:14px">Rien dans ce journal.</p><?php endif; ?>
</div>
