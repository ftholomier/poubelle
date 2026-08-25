<div class="topbar">
  <h1>Actualités</h1>
  <a class="btn btn--sm" href="<?= e(url('admin/actualites/nouveau')) ?>">+ Nouvel article</a>
</div>

<div class="panel">
  <?php if (!$rows): ?>
    <div class="empty"><b>Aucun article</b>Publiez votre première analyse de marché.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Titre</th><th>Catégorie</th><th>Statut</th><th>Publication</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $p): ?>
            <tr>
              <td>
                <a class="strong" href="<?= e(url('admin/actualites/' . $p['id'])) ?>"><?= e($p['title'] ?? '') ?></a>
                <div style="font-size:.78rem;color:var(--muted)">/actualites/<?= e($p['slug'] ?? '') ?></div>
              </td>
              <td style="color:var(--muted)"><?= e($p['category'] ?? '—') ?></td>
              <td>
                <span class="badge" style="color:<?= ($p['status'] ?? '') === 'published' ? '#35d07f' : '#8d99ae' ?>">
                  <i></i><?= ($p['status'] ?? '') === 'published' ? 'Publié' : 'Brouillon' ?>
                </span>
              </td>
              <td style="color:var(--muted);white-space:nowrap"><?= e(fr_date($p['published_at'] ?? '')) ?></td>
              <td style="text-align:right;white-space:nowrap">
                <?php if (($p['status'] ?? '') === 'published'): ?>
                  <a class="btn btn--sm btn--ghost" href="<?= e(url('actualites/' . ($p['slug'] ?? ''))) ?>" target="_blank" rel="noopener">Voir</a>
                <?php endif; ?>
                <a class="btn btn--sm btn--ghost" href="<?= e(url('admin/actualites/' . $p['id'])) ?>">Modifier</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
