<?php
/**
 * Liste des fiches métiers.
 *
 * @var array    $rows      lignes d'index
 * @var array    $families  catalogue des familles
 * @var array    $counts    slug => annonces en ligne
 * @var array    $modified  id => la fiche diffère de son texte d'origine
 * @var string[] $errors
 */
use App\Core\Csrf;
use App\Services\I18n;

$published = count(array_filter($rows, static fn(array $r) => $r['status'] === 'publish'));
?>
<div class="admin-head">
  <div>
    <h1><?= e(I18n::t('admin.trades')) ?></h1>
    <p><?= e(I18n::t('admin.trades_note', $published, count($rows))) ?></p>
  </div>
  <a class="btn btn-ghost-light btn-sm" href="<?= e(I18n::url('/metiers')) ?>" target="_blank" rel="noopener">Voir la mosaïque</a>
</div>

<?php if ($errors !== []): ?>
  <div class="notice notice-err" role="alert" tabindex="-1" data-error-focus>
    <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="admin-card">
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <?= Csrf::field('admin-trades') ?>
    <input type="hidden" name="action" value="create">
    <label class="field" style="flex:1 1 240px;margin:0">
      <span class="label">Nouveau métier</span>
      <input class="input" type="text" name="name" required placeholder="Régisseur d’orchestre">
    </label>
    <label class="field" style="flex:0 1 260px;margin:0">
      <span class="label">Famille</span>
      <select class="input" name="family" required>
        <?php foreach ($families as $key => $family): ?>
          <option value="<?= e($key) ?>"><?= e($family['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="btn btn-coral">Créer la fiche</button>
  </form>
  <p class="secret-help" style="margin:10px 0 0">
    La fiche est créée en brouillon : elle n’apparaît sur le site qu’une fois publiée.
  </p>
</div>

<div class="admin-card">
  <div class="table-scroll">
    <table class="admin-table">
      <thead>
        <tr><th>Métier</th><th>Famille</th><th>Adresse</th><th>Offres</th><th>Texte</th><th>État</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <?php $family = $families[$row['family']] ?? ['name' => $row['family']]; ?>
          <tr>
            <?php // Le nom mène à l'édition : la colonne des actions est la dernière
                  // d'un tableau qui défile, souvent hors de vue. ?>
            <td class="t"><a href="/admin/metier/<?= e($row['id']) ?>"><?= e($row['name']) ?></a></td>
            <td class="s"><?= e($family['name']) ?></td>
            <td class="s">/metiers/<?= e($row['slug']) ?></td>
            <td class="s"><?= (int) ($counts[$row['slug']] ?? 0) ?></td>
            <td>
              <?php if (!empty($modified[$row['id']])): ?>
                <span class="state state-wait">Modifié</span>
              <?php else: ?>
                <span class="state state-neutral">D’origine</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="state <?= $row['status'] === 'publish' ? 'state-ok' : 'state-wait' ?>">
                <?= e($row['status'] === 'publish' ? I18n::t('admin.state_publish') : I18n::t('admin.state_draft')) ?>
              </span>
            </td>
            <td class="actions">
              <a class="btn btn-ghost btn-sm" href="/admin/metier/<?= e($row['id']) ?>">Éditer</a>
              <?php if ($row['status'] === 'publish'): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/metiers/' . $row['slug'])) ?>"
                   target="_blank" rel="noopener">Voir</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
