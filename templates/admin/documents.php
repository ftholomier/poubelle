<?php
/** Documents de la base de connaissance. @var array $documents @var array $stats @var string $notice */
use App\Core\Csrf;
use App\Services\I18n;
use App\Support\Icon;
?>
<div class="admin-head">
  <div>
    <h1><?= e(I18n::t('admin.documents')) ?></h1>
    <p><?= e(number_format((int) $stats['chunks'], 0, ',', ' ')) ?> fragments ·
       <?= (int) $stats['documents'] ?> document(s) ·
       index du <?= e($stats['generated_at'] !== '' ? date('d/m/Y H:i', (int) strtotime((string) $stats['generated_at'])) : '—') ?></p>
  </div>
  <form method="post"><?= Csrf::field('admin-docs') ?>
    <button type="submit" name="action" value="reindex" class="btn btn-ghost-light btn-sm">Réindexer</button>
  </form>
</div>

<?php if ($notice !== ''): ?><div class="notice notice-ok" role="status"><?= e($notice) ?></div><?php endif; ?>

<div class="admin-card">
  <h2>Ajouter un document</h2>
  <p class="muted" style="font-size:14px;margin:6px 0 16px">
    Collez le texte du document. Régie s'en sert pour répondre, en citant sa source.
  </p>
  <form method="post">
    <?= Csrf::field('admin-docs') ?>
    <input type="hidden" name="action" value="add">
    <div class="grid-fields">
      <label class="field">
        <span class="label">Titre</span>
        <input class="input" type="text" name="title" required placeholder="Guide du GUSO 2026">
      </label>
      <label class="field">
        <span class="label">Type</span>
        <select class="select" name="kind">
          <option value="md">Markdown / texte</option>
          <option value="pdf">Extrait de PDF</option>
          <option value="json">JSON</option>
        </select>
      </label>
    </div>
    <label class="field" style="margin-top:16px">
      <span class="label">Contenu</span>
      <textarea class="textarea" name="text" rows="8" required
                placeholder="Collez ici le texte du document…"></textarea>
    </label>
    <button type="submit" class="btn btn-coral" style="margin-top:16px">
      <?= Icon::svg('plus', 15, '#fff', 2.2) ?> Ajouter et indexer
    </button>
  </form>
</div>

<div class="admin-card">
  <h2>Documents indexés</h2>
  <?php if ($documents === []): ?>
    <p class="muted" style="margin-top:12px">Aucun document. Régie s'appuie uniquement sur les pages, offres et profils du site.</p>
  <?php else: ?>
    <div class="table-scroll" style="margin-top:14px">
      <table class="admin-table">
        <thead><tr><th>Titre</th><th>Type</th><th>Taille</th><th>Ajouté</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($documents as $doc): ?>
            <tr>
              <td class="t"><?= e($doc['title']) ?></td>
              <td><span class="state state-neutral"><?= e(strtoupper((string) $doc['kind'])) ?></span></td>
              <td class="s"><?= e(number_format((int) $doc['chars'], 0, ',', ' ')) ?> car.</td>
              <td class="s"><?= e(date('d/m/Y', (int) strtotime((string) $doc['added_at']))) ?></td>
              <td class="actions">
                <form method="post" style="display:inline">
                  <?= Csrf::field('admin-docs') ?>
                  <input type="hidden" name="id" value="<?= e($doc['id']) ?>">
                  <button type="submit" name="action" value="remove" class="btn btn-ghost btn-sm"
                          data-confirm="Retirer ce document de la base de connaissance ?">Retirer</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
