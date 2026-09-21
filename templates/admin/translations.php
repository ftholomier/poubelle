<?php
/** Matrice de traduction. @var array $matrix @var bool $available @var string $notice */
use App\Core\Csrf;
use App\Services\I18n;

$cls = ['source' => 'state-neutral', 'fresh' => 'state-ok', 'stale' => 'state-wait', 'missing' => 'state-err'];
$lbl = ['source' => 'admin.state_source', 'fresh' => 'admin.state_fresh',
        'stale' => 'admin.state_stale', 'missing' => 'admin.state_missing'];
$languages = I18n::languages();
?>
<div class="admin-head">
  <div>
    <h1><?= e(I18n::t('admin.translations')) ?></h1>
    <p>Le français est la langue pivot. Une modification de la source rend les traductions obsolètes.</p>
  </div>
  <?php if ($available): ?>
    <form method="post"><?= Csrf::field('admin-i18n') ?>
      <button type="submit" class="btn btn-coral btn-sm"><?= e(I18n::t('admin.translate_all')) ?></button>
    </form>
  <?php endif; ?>
</div>

<?php if ($notice !== ''): ?><div class="notice notice-ok" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if (!$available): ?>
  <div class="notice notice-wait"><?= e(I18n::t('admin.translate_unavailable')) ?></div>
<?php endif; ?>

<div class="admin-card">
  <div class="table-scroll">
    <table class="admin-table">
      <thead>
        <tr><th>Page</th><?php foreach ($languages as $code => $meta): ?>
          <th><?= e(strtoupper((string) $code)) ?></th><?php endforeach; ?></tr>
      </thead>
      <tbody>
        <?php foreach ($matrix as $row): ?>
          <tr>
            <td><span class="t"><?= e(str_excerpt((string) $row['title'], 46)) ?></span><br>
                <span class="s">/<?= e($row['slug']) ?></span></td>
            <?php foreach ($languages as $code => $meta): $state = $row['states'][$code]['state'] ?? 'missing'; ?>
              <td><span class="state <?= e($cls[$state] ?? 'state-neutral') ?>"><?= e(I18n::t($lbl[$state] ?? 'admin.state_missing')) ?></span></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
