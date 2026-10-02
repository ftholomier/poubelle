<?php
use App\Core\Env;
use App\Core\Url;
/** @var array $schema @var array $others @var string $cronUrl @var string $cronCmd @var array $cron @var string $envPath */
?>
<div class="adm-head"><div><h1>Configuration <span class="serif">(.env)</span></h1><p>Paramètres techniques stockés dans <code><?= e($envPath) ?></code>, hors du dossier public. Chaque modification est journalisée et une copie de sauvegarde est conservée.</p></div>
  <div class="row-wrap">
    <form method="post" action="<?= e(Url::admin('reglages')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="test-mail"><button class="btn btn-sm" type="submit"><?= icon('mail', 16) ?> Tester l'envoi d'email</button></form>
    <form method="post" action="<?= e(Url::admin('reglages')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="test-ai"><button class="btn btn-sm" type="submit"><?= icon('sparkles', 16) ?> Tester Gemini</button></form>
  </div>
</div>
<form class="form" method="post" action="<?= e(Url::admin('reglages')) ?>" autocomplete="off" data-dirty-check>
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <?php foreach ($schema as $group => $keys): ?>
    <div class="box">
      <h2><?= e($group) ?></h2>
      <?php foreach ($keys as $key => $def): [$label, $type, $hint] = $def; $val = (string) Env::get($key, ''); $id = 'env-' . strtolower($key); ?>
        <div class="env-row">
          <div><label for="<?= e($id) ?>"><strong><?= e($label) ?></strong></label><br><code class="muted"><?= e($key) ?></code></div>
          <div>
            <?php if ($type === 'bool'): ?>
              <label class="switch"><input id="<?= e($id) ?>" type="checkbox" name="env[<?= e($key) ?>]" value="1"<?= in_array(strtolower($val), ['1', 'true', 'on', 'yes'], true) ? ' checked' : '' ?>> <?= in_array(strtolower($val), ['1', 'true', 'on', 'yes'], true) ? 'Activé' : 'Désactivé' ?></label>
            <?php elseif ($type === 'select'): ?>
              <select id="<?= e($id) ?>" name="env[<?= e($key) ?>]" class="input"><?php foreach ($def[3] as $k => $l): ?><option value="<?= e($k) ?>"<?= $val === (string) $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
            <?php elseif ($type === 'secret'): ?>
              <div class="secret-wrap"><input id="<?= e($id) ?>" class="input" type="password" name="env[<?= e($key) ?>]" value="" placeholder="<?= $val !== '' ? '•••••••• (défini — laisser vide pour conserver)' : 'Non défini' ?>" autocomplete="new-password"><button type="button" class="btn btn-xs" data-reveal="#<?= e($id) ?>">Afficher</button></div>
              <?php if ($val !== ''): ?><label class="check small mt-1"><input type="checkbox" name="env[<?= e($key) ?>__clear]" value="1"> Effacer cette valeur</label><?php endif; ?>
            <?php elseif ($type === 'readonly'): ?>
              <input id="<?= e($id) ?>" class="input" type="text" value="<?= e($val) ?>" readonly>
            <?php elseif ($type === 'locked'): ?>
              <span class="small muted"><?= $val !== '' ? '•••••••• (défini, non modifiable ici)' : 'Non défini' ?></span>
            <?php else: ?>
              <input id="<?= e($id) ?>" class="input" type="<?= $type === 'int' ? 'number' : ($type === 'email' ? 'email' : 'text') ?>" name="env[<?= e($key) ?>]" value="<?= e($val) ?>">
            <?php endif; ?>
            <?php if ($hint !== ''): ?><span class="hint"><?= e($hint) ?></span><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($others): ?><div class="box"><h2>Autres clés</h2><p class="small muted">Présentes dans le fichier mais non gérées par cette page (lecture seule).</p><ul class="list-rows"><?php foreach ($others as $k => $v): ?><li><code><?= e((string) $k) ?></code><span class="muted"><?= preg_match('/KEY|SECRET|PASSWORD|TOKEN/i', (string) $k) ? '••••' : e((string) $v) ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
  <div class="box">
    <h2><?= icon('lock', 18) ?> Confirmation</h2>
    <div class="field" style="max-width:360px"><label for="env-confirm">Votre mot de passe</label><input id="env-confirm" type="password" name="confirm_password" required autocomplete="current-password"><span class="hint">Requis pour modifier la configuration.</span></div>
  </div>
  <div class="form-actions"><button class="btn btn-coral" type="submit">Enregistrer la configuration</button></div>
</form>
<div class="box mt-3" id="cron">
  <h2>Tâches planifiées (cron)</h2>
  <p class="small">État : <?= $cron['ok'] ? '<span class="verified">✓ actif</span>' : '<strong style="color:var(--danger)">inactif</strong>' ?><?= $cron['last'] ? ' · dernier passage ' . e(ago($cron['last'])) . ' (' . e((string) $cron['source']) . ')' : '' ?></p>
  <p class="small"><strong>Recommandé</strong> — ajoutez cette ligne à la crontab du serveur (toutes les minutes) :</p>
  <pre class="log"><?= e($cronCmd) ?></pre>
  <p class="small"><strong>Sinon</strong> — appelez cette adresse toutes les minutes avec un service de cron en ligne (adresse secrète) :</p>
  <div class="row-wrap"><code class="small" style="word-break:break-all"><?= e(preg_replace('#/cron/.+$#', '/cron/••••••••', $cronUrl)) ?></code><button type="button" class="btn btn-xs" data-copy="<?= e($cronUrl) ?>">Copier l'adresse</button></div>
  <form class="row-wrap mt-2" method="post" action="<?= e(Url::admin('reglages')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="rotate-cron"><input class="input" style="max-width:240px;min-height:38px" type="password" name="confirm_password" placeholder="Mot de passe" required autocomplete="current-password"><button class="btn btn-xs" type="submit">Générer un nouveau jeton</button></form>
  <?php if (!empty($cron['tasks'])): ?><ul class="list-rows mt-2"><?php foreach (App\Services\Cron::TASKS as $k => [$l]): $t = $cron['tasks'][$k] ?? null; ?><li><span><?= e($l) ?></span><span class="muted"><?= $t ? e(ago((string) $t['at'])) . ($t['ok'] ? '' : ' · erreur') : 'jamais' ?></span></li><?php endforeach; ?></ul><?php endif; ?>
</div>
