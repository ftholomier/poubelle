<?php
/** CV déposés. @var array $items */
use App\Core\Csrf;
use App\Services\Auth;
use App\Services\I18n;
?>
<div class="admin-head">
  <div><h1><?= e(I18n::t('admin.cvs')) ?></h1><p><?= count($items) ?> profil(s)</p></div>
</div>

<div class="admin-card">
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Nom</th><th>Métier</th><th>Ville</th><th>Fichier</th><th>État</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($items as $cv): ?>
          <tr>
            <td><span class="t"><?= e(str_excerpt((string) $cv['name'], 40)) ?></span></td>
            <td class="s"><?= e(str_excerpt((string) ($cv['title'] ?: '—'), 40)) ?></td>
            <td class="s"><?= e($cv['location']['city'] ?: '—') ?></td>
            <td>
              <?php if (($cv['file']['path'] ?? '') !== ''): ?>
                <span class="state state-ok">Présent</span>
              <?php elseif (($cv['file']['legacy_url'] ?? '') !== ''): ?>
                <span class="state state-wait">À récupérer</span>
              <?php else: ?>
                <span class="state state-neutral">Aucun</span>
              <?php endif; ?>
            </td>
            <td><span class="state <?= $cv['status'] === 'publish' && $cv['listed'] ? 'state-ok' : 'state-wait' ?>">
              <?= e($cv['status'] === 'publish' && $cv['listed'] ? I18n::t('admin.state_publish') : I18n::t('admin.state_draft')) ?>
            </span></td>
            <td class="actions">
              <?php if ($cv['status'] === 'publish' && $cv['listed']): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/cv/' . $cv['slug'])) ?>" target="_blank" rel="noopener">Voir</a>
              <?php endif; ?>
              <form method="post" style="display:inline">
                <?= Csrf::field('admin-cvs') ?>
                <input type="hidden" name="id" value="<?= e($cv['id']) ?>">
                <button type="submit" name="action" value="<?= $cv['status'] === 'publish' ? 'unpublish' : 'publish' ?>"
                        class="btn btn-ghost btn-sm"><?= $cv['status'] === 'publish' ? 'Retirer' : 'Publier' ?></button>
              </form>
              <?php if (Auth::isAdmin()): ?>
                <form method="post" style="display:inline">
                  <?= Csrf::field('admin-cvs') ?>
                  <input type="hidden" name="id" value="<?= e($cv['id']) ?>">
                  <button type="submit" name="action" value="delete" class="btn btn-ghost btn-sm"
                          data-confirm="Supprimer ce profil et ses fichiers ? Un instantané est créé avant.">Supprimer</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
