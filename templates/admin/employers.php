<?php
/** Employeurs. @var array $items */
use App\Services\I18n;
?>
<div class="admin-head">
  <div><h1><?= e(I18n::t('admin.employers')) ?></h1><p><?= count($items) ?> structure(s)</p></div>
</div>
<div class="admin-card">
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Nom</th><th>Ville</th><th>Site</th><th>Offres</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($items as $employer): ?>
          <tr>
            <td class="t"><?= e(str_excerpt((string) $employer['name'], 50)) ?></td>
            <td class="s"><?= e($employer['city'] ?: '—') ?></td>
            <td class="s"><?= $employer['website'] !== ''
                ? '<a href="' . e($employer['website']) . '" target="_blank" rel="noopener nofollow">lien</a>' : '—' ?></td>
            <td><span class="state <?= (int) $employer['job_count'] > 0 ? 'state-ok' : 'state-neutral' ?>">
              <?= (int) $employer['job_count'] ?></span></td>
            <td class="actions">
              <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/employeur/' . $employer['slug'])) ?>"
                 target="_blank" rel="noopener">Voir</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
