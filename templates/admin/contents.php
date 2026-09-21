<?php
/** Liste des pages. @var array $pages */
use App\Core\Csrf;
use App\Services\I18n;
?>
<div class="admin-head"><h1><?= e(I18n::t('admin.contents')) ?></h1></div>

<div class="admin-card">
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <?= Csrf::field('admin-contents') ?>
    <input type="hidden" name="action" value="create">
    <label class="field" style="flex:1 1 260px;margin:0">
      <span class="label">Titre de la nouvelle page</span>
      <input class="input" type="text" name="title" required placeholder="Charte de modération">
    </label>
    <button type="submit" class="btn btn-coral">Créer</button>
  </form>
</div>

<div class="admin-card">
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Titre</th><th>Adresse</th><th>Mise à jour</th><th>État</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($pages as $page): ?>
          <tr>
            <td class="t"><?= e($page['title']) ?></td>
            <td class="s">/<?= e($page['slug']) ?></td>
            <td class="s"><?= e($page['updated_at'] !== '' ? date('d/m/Y', (int) strtotime((string) $page['updated_at'])) : '—') ?></td>
            <td><span class="state <?= $page['status'] === 'publish' ? 'state-ok' : 'state-wait' ?>">
              <?= e($page['status'] === 'publish' ? I18n::t('admin.state_publish') : I18n::t('admin.state_draft')) ?>
            </span></td>
            <td class="actions">
              <a class="btn btn-ghost btn-sm" href="/admin/contenu/<?= e($page['slug']) ?>">Éditer</a>
              <?php if ($page['status'] === 'publish'): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/' . $page['slug'])) ?>" target="_blank" rel="noopener">Voir</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
