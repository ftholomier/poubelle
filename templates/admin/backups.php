<?php
/** Sauvegardes et journal. @var array $backups @var array $journal @var string $notice */
use App\Core\Csrf;
use App\Services\Auth;
use App\Services\I18n;
?>
<div class="admin-head">
  <div>
    <h1><?= e(I18n::t('admin.backups')) ?></h1>
    <p>Un instantané est créé automatiquement avant chaque publication et chaque suppression.</p>
  </div>
  <form method="post"><?= Csrf::field('admin-backups') ?>
    <button type="submit" name="action" value="create" class="btn btn-coral btn-sm"><?= e(I18n::t('admin.snapshot')) ?></button>
  </form>
</div>

<?php if ($notice !== ''): ?><div class="notice notice-ok" role="status"><?= e($notice) ?></div><?php endif; ?>

<div class="admin-card">
  <h2>Instantanés</h2>
  <?php if ($backups === []): ?>
    <p class="muted" style="margin-top:12px">Aucun instantané pour l'instant.</p>
  <?php else: ?>
  <div class="table-scroll" style="margin-top:14px">
    <table class="admin-table">
      <thead><tr><th>Date</th><th>Motif</th><th>Taille</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($backups as $backup): ?>
          <tr>
            <td class="t"><?= e(date('d/m/Y H:i', (int) strtotime((string) $backup['created_at']))) ?></td>
            <td class="s"><?= e($backup['reason']) ?></td>
            <td class="s"><?= e(number_format($backup['size'] / 1024, 0, ',', ' ')) ?> Ko</td>
            <td class="actions">
              <?php if (Auth::isAdmin()): ?>
                <form method="post" style="display:inline">
                  <?= Csrf::field('admin-backups') ?>
                  <input type="hidden" name="name" value="<?= e($backup['name']) ?>">
                  <button type="submit" name="action" value="restore" class="btn btn-ghost btn-sm"
                          data-confirm="Restaurer cet instantané ? L'état actuel est sauvegardé avant.">
                    <?= e(I18n::t('admin.restore')) ?>
                  </button>
                </form>
                <form method="post" style="display:inline">
                  <?= Csrf::field('admin-backups') ?>
                  <input type="hidden" name="name" value="<?= e($backup['name']) ?>">
                  <button type="submit" name="action" value="delete" class="btn btn-ghost btn-sm"
                          data-confirm="Supprimer cet instantané ?">Supprimer</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="admin-card">
  <h2>Journal</h2>
  <div class="table-scroll" style="margin-top:14px">
    <table class="admin-table">
      <thead><tr><th>Date</th><th>Évènement</th><th>Détail</th></tr></thead>
      <tbody>
        <?php foreach ($journal as $entry): ?>
          <tr>
            <td class="s"><?= e(date('d/m/Y H:i', (int) strtotime((string) $entry['at']))) ?></td>
            <td class="t"><?= e((string) $entry['event']) ?></td>
            <td class="s"><?= e(str_excerpt(json_encode($entry['context'] ?? [], JSON_UNESCAPED_UNICODE) ?: '', 90)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
