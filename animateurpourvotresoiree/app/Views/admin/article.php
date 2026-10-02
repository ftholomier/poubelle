<?php
use App\Core\Url;
use App\Services\Blog;
/** @var ?array $a @var bool $aiOn */
$id = (int) ($a['id'] ?? 0);
$img = Blog::imageUrl($a['image'] ?? null, 'sm');
?>
<p><a class="link small" href="<?= e(Url::admin('blog')) ?>">← Blog</a></p>
<div class="adm-head">
  <div><h1><?= e($a['title'] ?? 'Nouvel article') ?></h1><?php if ($a): ?><p><?= ($a['status'] ?? '') === 'published' ? '<a href="' . e(Url::blog((string) $a['slug'])) . '" target="_blank" rel="noopener">Voir l\'article ↗</a>' : 'Brouillon' ?><?= !empty($a['ai_generated']) ? ' · rédigé avec l\'IA' : '' ?></p><?php endif; ?></div>
  <?php if ($a): ?><form method="post" action="<?= e(Url::admin('blog/' . $id)) ?>" data-confirm="Supprimer définitivement cet article ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm" type="submit">Supprimer</button></form><?php endif; ?>
</div>
<form class="adm-cols" method="post" action="<?= e(Url::admin($a ? 'blog/' . $id : 'blog/nouveau')) ?>" enctype="multipart/form-data" data-dirty-check>
  <div class="form">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <div class="box">
      <div class="field"><label for="ar-title">Titre *</label><input id="ar-title" type="text" name="title" maxlength="160" required value="<?= e((string) ($a['title'] ?? '')) ?>"></div>
      <div class="field"><label for="ar-slug">Adresse : /blog/…/</label><input id="ar-slug" type="text" name="slug" maxlength="80" value="<?= e((string) ($a['slug'] ?? '')) ?>" data-slug-from="#ar-title"></div>
      <div class="field"><label for="ar-ex">Chapô (résumé affiché dans les listes)</label><textarea id="ar-ex" name="excerpt" rows="2" maxlength="300"><?= e((string) ($a['excerpt'] ?? '')) ?></textarea></div>
      <div class="field"><label for="ar-body">Contenu</label><textarea id="ar-body" name="body" data-editor="full" data-upload="<?= e(Url::admin('upload')) ?>" rows="20"><?= e((string) ($a['body'] ?? '')) ?></textarea></div>
    </div>
    <div class="box">
      <h2>Référencement</h2>
      <div class="serp" id="serp-ar"><div class="u"><?= e(Url::abs(Url::blog((string) ($a['slug'] ?? 'mon-article')))) ?></div><div class="t" data-default="<?= e((string) ($a['title'] ?? 'Titre de l\'article')) ?>"><?= e((string) (($a['seo']['title'] ?? '') ?: ($a['title'] ?? 'Titre de l\'article'))) ?></div><div class="d" data-default="<?= e((string) ($a['excerpt'] ?? '')) ?>"><?= e((string) (($a['seo']['description'] ?? '') ?: ($a['excerpt'] ?? ''))) ?></div></div>
      <div class="field mt-1"><label for="ar-st">Titre SEO (facultatif)</label><input id="ar-st" type="text" name="seo_title" maxlength="70" value="<?= e((string) ($a['seo']['title'] ?? '')) ?>" data-seo-count="60" data-serp="#serp-ar" data-serp-part="t"></div>
      <div class="field"><label for="ar-sd">Meta description</label><textarea id="ar-sd" name="seo_description" rows="2" maxlength="170" data-seo-count="160" data-serp="#serp-ar" data-serp-part="d"><?= e((string) ($a['seo']['description'] ?? '')) ?></textarea></div>
    </div>
  </div>
  <aside>
    <div class="box">
      <h2>Publication</h2>
      <div class="field"><label for="ar-status">Statut</label><select id="ar-status" name="status"><option value="draft"<?= ($a['status'] ?? 'draft') === 'draft' ? ' selected' : '' ?>>Brouillon</option><option value="published"<?= ($a['status'] ?? '') === 'published' ? ' selected' : '' ?>>Publié</option></select></div>
      <div class="field"><label for="ar-date">Date de publication</label><input id="ar-date" type="datetime-local" name="published_at" value="<?= e(!empty($a['published_at']) ? date('Y-m-d\TH:i', (int) strtotime((string) $a['published_at'])) : date('Y-m-d\TH:i')) ?>"><span class="hint">Une date future programme la publication.</span></div>
      <div class="field"><label for="ar-cat">Catégorie</label><select id="ar-cat" name="category"><?php foreach (Blog::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= ($a['category'] ?? 'conseils') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="ar-author">Auteur</label><input id="ar-author" type="text" name="author" maxlength="80" value="<?= e((string) ($a['author'] ?? '')) ?>"></div>
      <label class="switch"><input type="checkbox" name="sponsored" value="1"<?= !empty($a['sponsored']) ? ' checked' : '' ?>> Article sponsorisé (liens nofollow)</label>
      <div class="form-actions"><button class="btn btn-coral btn-block" type="submit">Enregistrer</button></div>
    </div>
    <div class="box">
      <h2>Image de couverture</h2>
      <?php if ($img): ?><img src="<?= e($img) ?>" alt="" style="border:2px solid var(--ink);border-radius:12px;margin-bottom:10px"><?php endif; ?>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="small">
      <div class="field mt-1"><label for="ar-alt">Texte alternatif</label><input id="ar-alt" type="text" name="image_alt" maxlength="160" value="<?= e((string) ($a['image_alt'] ?? '')) ?>"></div>
    </div>
    <?php if (!empty($a['legacy_url'])): ?><div class="box"><h2>Ancienne adresse</h2><p class="small"><code><?= e((string) $a['legacy_url']) ?></code> est redirigée (301) vers cet article.</p></div><?php endif; ?>
  </aside>
</form>
