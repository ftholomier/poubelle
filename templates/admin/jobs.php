<?php
/** Offres d'emploi. @var array $items */
use App\Core\Csrf;
use App\Services\Auth;
use App\Services\I18n;

$state = ['publish' => ['state-ok', 'admin.state_publish'], 'draft' => ['state-wait', 'admin.state_draft'],
          'expired' => ['state-neutral', 'admin.state_expired']];
?>
<div class="admin-head">
  <div><h1><?= e(I18n::t('admin.jobs')) ?></h1><p><?= count($items) ?> annonce(s)</p></div>
</div>

<div class="admin-card">
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Intitulé</th><th>Structure</th><th>Lieu</th><th>Date</th><th>État</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($items as $job): $s = $state[$job['status']] ?? ['state-neutral', 'admin.state_draft']; ?>
          <tr>
            <td><span class="t"><?= e(str_excerpt((string) $job['title'], 64)) ?></span></td>
            <td class="s"><?= e($job['company']['name'] ?: '—') ?></td>
            <td class="s"><?= e($job['location']['city'] ?: ($job['location']['region'] ?: '—')) ?></td>
            <td class="s"><?= e(($job['published_at'] ?: $job['created_at']) !== ''
                  ? date('d/m/Y', (int) strtotime((string) ($job['published_at'] ?: $job['created_at']))) : '—') ?></td>
            <td><span class="state <?= e($s[0]) ?>"><?= e(I18n::t($s[1])) ?></span></td>
            <td class="actions">
              <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/offre/' . $job['slug'])) ?>" target="_blank" rel="noopener">Voir</a>
              <form method="post" style="display:inline">
                <?= Csrf::field('admin-jobs') ?>
                <input type="hidden" name="id" value="<?= e($job['id']) ?>">
                <button type="submit" name="action" value="<?= $job['status'] === 'publish' ? 'unpublish' : 'publish' ?>"
                        class="btn btn-ghost btn-sm"><?= $job['status'] === 'publish' ? 'Dépublier' : 'Publier' ?></button>
              </form>
              <?php if (Auth::isAdmin()): ?>
                <form method="post" style="display:inline">
                  <?= Csrf::field('admin-jobs') ?>
                  <input type="hidden" name="id" value="<?= e($job['id']) ?>">
                  <button type="submit" name="action" value="delete" class="btn btn-ghost btn-sm"
                          data-confirm="Supprimer définitivement cette annonce ? Un instantané est créé avant.">Supprimer</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
