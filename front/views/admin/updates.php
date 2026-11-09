<?php
/** Mises à jour depuis GitHub. @var ?array $check */
$sha = fn(?string $s) => $s ? substr($s, 0, 7) : '—';
$when = fn($d) => $d ? date('d/m/Y à H\hi', is_numeric($d) ? (int) $d : (int) strtotime((string) $d)) : '';
$installed = Updater::installed();
$latest = $check['latest'] ?? null;
$commits = $check['commits'] ?? [];
$sync = $check['sync'] ?? null;
$differ = $check ? Updater::differing($check) : null;
$available = Updater::available() !== null;
$newVersion = $available && Updater::isNewVersion($check ?? []);
$env = ['zip' => class_exists(ZipArchive::class), 'curl' => function_exists('curl_init'), 'writable' => is_writable(APP_ROOT . '/src') && is_writable(APP_ROOT . '/storage')];
$title = !$check ? 'Vérifiez la dernière version' : (!empty($check['error']) ? 'Vérification impossible' : ($newVersion ? 'Nouvelle version disponible' : ($available ? 'Le serveur diffère de GitHub' : 'Le site est à jour')));
$button = $newVersion ? 'Mettre à jour maintenant' : 'Synchroniser avec GitHub';
$history = array_slice(Updater::history(), 0, 12);
$backups = Updater::backups();
?>
<div class="bo-actions" style="margin-bottom:16px">
  <button class="b b--l" type="button" data-action="update.check">⟳ Vérifier maintenant</button>
  <span class="bo-muted"><?= e(Updater::repo()) ?> · branche <code><?= e(Updater::branch()) ?></code></span>
</div>

<?php if (!$env['zip'] || !$env['curl'] || !$env['writable']): ?>
  <div class="card card--warn">Le serveur ne permet pas encore les mises à jour automatiques :
    <?= !$env['zip'] ? 'extension PHP <b>Zip</b> absente. ' : '' ?><?= !$env['curl'] ? 'extension PHP <b>cURL</b> absente. ' : '' ?><?= !$env['writable'] ? 'les dossiers <b>src/</b> ou <b>storage/</b> ne sont pas modifiables par PHP.' : '' ?></div>
<?php endif; ?>

<div class="kpis">
  <div class="kpi"><b><?= $installed ? e($sha($installed['sha'])) : 'FTP' ?></b><span>version installée<?= $installed && !empty($installed['date']) ? ' · du ' . e(date('d/m/Y', (int) strtotime((string) $installed['date']))) : ($installed ? '' : ' (pas encore reconnue)') ?></span></div>
  <div class="kpi"><b><?= e($sha($latest['sha'] ?? null)) ?></b><span>dernière version sur GitHub<?= $latest && !empty($latest['date']) ? ' · du ' . e(date('d/m/Y', (int) strtotime((string) $latest['date']))) : '' ?></span></div>
  <div class="kpi"><b><?= $differ !== null && !$newVersion ? (int) $differ : ($newVersion ? count($commits) : 0) ?></b><span><?= $differ !== null && !$newVersion ? 'fichier(s) à remplacer sur ' . (int) ($sync['total'] ?? 0) . ' comparés' : 'changement(s) à installer' ?><?= $check ? ' · vérifié le ' . e($when($check['at'] ?? null)) : '' ?></span></div>
</div>

<?php if ($check && !empty($check['error'])): ?><div class="card card--warn">Vérification impossible : <?= e($check['error']) ?></div><?php endif; ?>

<div class="cols">
  <div class="card">
    <h2><?= e($title) ?></h2>
    <?php if ($newVersion && $commits): ?>
      <p class="bo-muted">Changements depuis la version installée<?= count($commits) >= 80 ? ' (les 80 derniers)' : '' ?> :</p>
      <div class="commits">
        <?php foreach ($commits as $c): ?>
          <div class="commit"><b><?= e($c['message'] ?: $sha($c['sha'])) ?></b><small><?= e($when($c['date'] ?? null)) ?> · <?= e($sha($c['sha'])) ?></small>
            <?php if (!empty($c['details'])): ?><ul><?php foreach ($c['details'] as $d): ?><li><?= e((string) preg_replace('/^[-•\s]+/u', '', $d)) ?></li><?php endforeach; ?></ul><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($differ === 0): ?>
      <p><span class="ok">✓</span> Fichier par fichier, le code du serveur est identique à la version <?= e($sha($sync['sha'])) ?> de GitHub (<?= (int) $sync['total'] ?> fichiers comparés).</p>
    <?php elseif ($differ): ?>
      <p><b><?= (int) $differ ?> fichier(s) du serveur diffèrent de la version <?= e($sha($sync['sha'])) ?></b> (sur <?= (int) $sync['total'] ?> comparés)<?= $newVersion ? '' : ' : envoi FTP incomplet ou fichier retouché' ?>. « <?= e($button) ?> » les remplace.</p>
      <details><summary>Voir les fichiers</summary><ul class="files"><?php foreach ((array) $sync['differ'] as $path => $st): ?><li><code><?= e((string) $path) ?></code> <span class="bo-muted"><?= $st === 'absent' ? 'absent du serveur' : 'différent' ?></span></li><?php endforeach; ?></ul></details>
    <?php elseif (is_array($sync) && !empty($sync['error'])): ?>
      <p class="bo-muted">Comparaison fichier par fichier impossible pour l'instant : <?= e($sync['error']) ?></p>
    <?php endif; ?>
    <?php if (!empty($sync['kept'])): ?><p class="bo-muted">Réglé à la main sur le serveur et gardé : <?= e(implode(', ', (array) $sync['kept'])) ?>.</p><?php endif; ?>
    <?php if ($available && $env['zip'] && $env['curl'] && $env['writable']): ?>
      <div class="bo-actions">
        <button class="b b--y" type="button" data-action="update.apply" data-confirm="Installer la mise à jour maintenant ?" data-busy="Installation en cours… (jusqu'à une minute)"><?= e($button) ?></button>
        <span class="bo-muted">Une sauvegarde des fichiers remplacés est faite avant.</span>
      </div>
    <?php endif; ?>
  </div>
  <div>
    <div class="card">
      <h2>Ce qu'une mise à jour change</h2>
      <div class="bo-small">
        <p><b>Remplacé :</b> le code du site (<code>src/</code>, <code>views/</code>, <code>public/</code>, <code>config/</code>, <code>content/</code>), seulement les fichiers qui ont changé.</p>
        <p><b>Jamais touché :</b> <code>storage/</code> (contenus modifiés dans ce back-office, demandes, comptes, clés), <code>public/uploads/</code> (fichiers téléversés), <code>config/config.local.php</code>. Un <code>config.php</code> ou <code>.htaccess</code> réglé à la main est gardé.</p>
        <p><b>Sécurité :</b> les fichiers remplacés sont sauvegardés avant ; en cas d'échec, l'ancienne version est remise aussitôt. « Revenir à cette version » ci-dessous annule une mise à jour.</p>
        <p><b>Vérification automatique</b> toutes les 3 heures avec la tâche planifiée (rubrique Sauvegarde) : le menu « Mises à jour » affiche une pastille quand une version est disponible.</p>
      </div>
    </div>
    <form class="card bo-form" data-api="update.settings">
      <h2>Réglages</h2>
      <div class="f"><label class="f-label" for="up-r">Dépôt GitHub</label><input id="up-r" name="data[update_repo]" value="<?= e((string) Settings::get('update_repo', '')) ?>" placeholder="<?= e(Updater::REPO) ?>"></div>
      <div class="f"><label class="f-label" for="up-b">Branche</label><input id="up-b" name="data[update_branch]" value="<?= e((string) Settings::get('update_branch', '')) ?>" placeholder="<?= e(Updater::BRANCH) ?>"></div>
      <div class="f"><label class="f-label" for="up-t">Jeton GitHub (dépôt privé)</label><input id="up-t" type="password" name="data[github_token]" autocomplete="new-password" placeholder="<?= Updater::hasToken() ? '•••••••• (enregistré — laisser vide pour garder)' : 'Jeton « fine-grained » en lecture seule sur le dépôt' ?>">
        <?php if (Updater::hasToken()): ?><label class="f-check" style="margin-top:6px"><input type="checkbox" name="data[_clear][github_token]" value="1"> <span>Effacer le jeton</span></label><?php endif; ?>
        <div class="f-help">GitHub › Settings › Developer settings › Fine-grained tokens : accès au dépôt, permission « Contents : Read-only ».</div></div>
      <button class="b b--l" type="submit">Enregistrer</button>
    </form>
  </div>
</div>

<div class="card">
  <h2>Historique des versions</h2>
  <?php if (!$history): ?><p class="bo-muted">Aucune mise à jour appliquée depuis cette page pour l'instant.</p><?php else: ?>
  <table class="tbl">
    <thead><tr><th>Date</th><th>Par</th><th>Version</th><th>Fichiers</th><th>Note</th></tr></thead>
    <tbody>
      <?php foreach ($history as $h): ?>
        <tr class="<?= !empty($h['error']) ? 'is-err' : '' ?>">
          <td><?= e($when($h['at'] ?? null)) ?></td>
          <td><?= e((string) ($h['by'] ?? '')) ?></td>
          <td><code><?= e($sha($h['from'] ?? null)) ?></code> → <code><?= e($sha($h['to'] ?? null)) ?></code></td>
          <td><?= (int) ($h['updated'] ?? 0) ?> remplacé(s)<?= !empty($h['deleted']) ? ', ' . (int) $h['deleted'] . ' supprimé(s)' : '' ?></td>
          <td><?= e((string) ($h['message'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php if ($backups): ?>
<div class="card">
  <h2>Sauvegardes (retour arrière)</h2>
  <table class="tbl">
    <thead><tr><th>Date</th><th>Avant la version</th><th>Fichiers</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($backups as $b): ?>
        <tr>
          <td><?= e($when($b['meta']['at'] ?? null)) ?></td>
          <td><code><?= e($sha($b['meta']['to'] ?? null)) ?></code> (depuis <code><?= e($sha($b['meta']['from']['sha'] ?? null)) ?></code>)</td>
          <td><?= (int) $b['files'] ?></td>
          <td class="r"><button class="b b--l" type="button" data-action="update.rollback" data-backup="<?= e($b['name']) ?>" data-confirm="Revenir à la version d'avant cette mise à jour ?">Revenir à cette version</button></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
