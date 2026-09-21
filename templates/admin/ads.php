<?php
/** Emplacements publicitaires. @var array $slots @var string $client @var string $notice */
use App\Core\Csrf;
use App\Services\I18n;
?>
<div class="admin-head">
  <div>
    <h1><?= e(I18n::t('admin.ads')) ?></h1>
    <p>Compte AdSense : <?= $client !== '' ? e($client) : '<em>non configuré (config/secrets.php)</em>' ?></p>
  </div>
</div>

<?php if ($notice !== ''): ?><div class="notice notice-ok" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($client === ''): ?>
  <div class="notice notice-wait">
    Sans identifiant AdSense, les emplacements affichent le cadre en pointillés de la maquette.
    Les réglages ci-dessous restent actifs.
  </div>
<?php endif; ?>

<form method="post" class="admin-card">
  <?= Csrf::field('admin-ads') ?>
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Emplacement</th><th>Format</th><th>Identifiant</th><th>Actif</th></tr></thead>
      <tbody>
        <?php foreach ($slots as $name => $slot): ?>
          <tr>
            <td><span class="t"><?= e($slot['label']) ?></span><br><span class="s"><?= e($name) ?></span></td>
            <td class="s"><?= e($slot['format']) ?></td>
            <td><?= $slot['slot'] !== ''
                ? '<span class="state state-ok">' . e($slot['slot']) . '</span>'
                : '<span class="state state-neutral">—</span>' ?></td>
            <td>
              <label class="check">
                <input type="checkbox" name="slot_<?= e($name) ?>" value="1" <?= $slot['enabled'] ? 'checked' : '' ?>>
                <span class="visually-hidden"><?= e($slot['label']) ?></span>
              </label>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button type="submit" class="btn btn-coral" style="margin-top:18px"><?= e(I18n::t('admin.save')) ?></button>
</form>
