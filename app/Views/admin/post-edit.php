<div class="topbar">
  <h1><?= e($title) ?></h1>
  <a class="btn btn--sm btn--ghost" href="<?= e(url('admin/actualites')) ?>">← Retour</a>
</div>

<form method="post" data-dirty-guard>
  <?= Csrf::field() ?>
  <div class="editor" style="grid-template-columns:1fr 300px">
    <div class="panel">
      <div class="field">
        <label for="title">Titre</label>
        <input class="input" id="title" name="title" value="<?= e($row['title'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label for="slug">Adresse (slug)</label>
        <input class="input" id="slug" name="slug" value="<?= e($row['slug'] ?? '') ?>" placeholder="Généré depuis le titre si vide">
      </div>
      <div class="field">
        <label for="excerpt">Chapô</label>
        <textarea class="textarea" id="excerpt" name="excerpt" rows="2"><?= e($row['excerpt'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label for="body">Corps de l’article</label>
        <small class="help">HTML autorisé : &lt;p&gt; &lt;h2&gt; &lt;h3&gt; &lt;ul&gt; &lt;li&gt; &lt;strong&gt; &lt;em&gt; &lt;blockquote&gt; &lt;a&gt;. Tout le reste est nettoyé à l’enregistrement.</small>
        <textarea class="textarea textarea--code" id="body" name="body"><?= e($row['body'] ?? '') ?></textarea>
      </div>
    </div>

    <div>
      <div class="panel">
        <div class="panel__head"><h2>Publication</h2></div>
        <div class="field">
          <label for="status">Statut</label>
          <select class="select" id="status" name="status">
            <option value="draft" <?= ($row['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Brouillon</option>
            <option value="published" <?= ($row['status'] ?? '') === 'published' ? 'selected' : '' ?>>Publié</option>
          </select>
        </div>
        <div class="field">
          <label for="published_at">Date de publication</label>
          <input class="input" id="published_at" name="published_at" type="datetime-local"
                 value="<?= e(date('Y-m-d\TH:i', strtotime((string) ($row['published_at'] ?? 'now')) ?: time())) ?>">
        </div>
        <div class="field">
          <label for="category">Catégorie</label>
          <input class="input" id="category" name="category" value="<?= e($row['category'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="author">Auteur</label>
          <input class="input" id="author" name="author" value="<?= e($row['author'] ?? '') ?>">
        </div>
        <button class="btn" style="width:100%" type="submit">Enregistrer</button>
      </div>

      <?php if (!empty($row['id'])): ?>
        <div class="panel">
          <div class="panel__head"><h2>Supprimer</h2></div>
          <p style="font-size:.85rem;color:var(--muted);margin-bottom:12px">Action définitive.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php if (!empty($row['id'])): ?>
  <form method="post" action="<?= e(url('admin/actualites/' . $row['id'] . '/supprimer')) ?>"
        onsubmit="return confirm('Supprimer définitivement cet article ?')" style="margin-top:-12px">
    <?= Csrf::field() ?>
    <button class="btn btn--danger btn--sm" type="submit">Supprimer l’article</button>
  </form>
<?php endif; ?>
