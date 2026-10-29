<?php
/**
 * Mise à jour du code depuis GitHub.
 * @var array $installed @var array $latest @var string $branch @var array $backups @var array|null $result
 */
use App\Core\Csrf;

$short = static fn(string $sha): string => $sha !== '' ? substr($sha, 0, 7) : '—';
$upToDate = $installed['sha'] !== '' && $installed['sha'] === $latest['sha'];
?>
<div class="admin-head">
  <div>
    <h1>Mise à jour</h1>
    <p>Installe la dernière version du site publiée sur GitHub (branche <?= e($branch) ?>), sans FTP.</p>
  </div>
</div>

<?php if ($result !== null): ?>
  <div class="notice <?= $result['ok'] ? 'notice-ok' : 'notice-err' ?>" role="status"><?= e($result['message']) ?></div>
<?php endif; ?>

<div class="admin-card">
  <table class="admin-table table-kv">
    <tbody>
      <tr><td><span class="t">Version installée</span></td>
          <td><?= e($short($installed['sha'])) ?><?= $installed['at'] !== '' ? ' · ' . e(date('d/m/Y H:i', (int) strtotime($installed['at']))) : ' · inconnue (envoyée par FTP)' ?>
              <?= $installed['message'] !== '' ? '<br><span class="s">' . e($installed['message']) . '</span>' : '' ?></td></tr>
      <tr><td><span class="t">Dernière version sur GitHub</span></td>
          <td><?php if ($latest['error'] !== ''): ?><span class="state state-err"><?= e($latest['error']) ?></span>
              <?php else: ?><?= e($short($latest['sha'])) ?> · <?= e(date('d/m/Y H:i', (int) strtotime($latest['date']))) ?>
              <br><span class="s"><?= e($latest['message']) ?></span><?php endif; ?></td></tr>
    </tbody>
  </table>

  <form method="post" style="margin-top:18px">
    <?= Csrf::field('admin-update') ?>
    <button type="submit" name="action" value="update" class="btn btn-coral"
            <?= $latest['error'] !== '' ? 'disabled' : '' ?>
            data-confirm="Installer la dernière version ? Le code actuel est sauvegardé avant, et restaurable en un clic.">
      <?= $upToDate ? 'Réinstaller la dernière version' : 'Mettre à jour maintenant' ?>
    </button>
  </form>
  <p class="muted" style="font-size:13px;margin:14px 0 0">
    Le code est remplacé (app, bin, templates, public, et les fichiers de config nouveaux), ainsi que les pages du site (FAQ, mentions, conditions…) — sauf celles que vous avez retouchées dans « Pages & contenus » depuis la dernière mise à jour, laissées telles quelles et signalées.
    Les données (offres, CV, comptes, fiches métiers modifiées), les réglages existants de config/config.php et les clés d’API ne sont jamais touchés.
    Le code en place est archivé avant chaque mise à jour ; les cinq dernières archives sont gardées.
  </p>
</div>

<?php if ($backups !== []): ?>
<div class="admin-card">
  <h2>Revenir à une version précédente</h2>
  <div class="table-scroll" style="margin-top:12px">
    <table class="admin-table">
      <thead><tr><th>Sauvegarde du code</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($backups as $b): ?>
          <tr><td class="s"><?= e($b) ?></td>
            <td class="actions"><form method="post"><?= Csrf::field('admin-update') ?>
              <input type="hidden" name="backup" value="<?= e($b) ?>">
              <button type="submit" name="action" value="rollback" class="btn btn-ghost btn-sm"
                      data-confirm="Restaurer ce code ? La version actuelle sera remplacée.">Restaurer</button></form></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
