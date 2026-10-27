<?php /** @var array $p @var array $themes @var string $csrf @var bool $canImage */ ?>
<div class="toolbar">
  <a href="/admin/blog" class="back">← Tous les articles</a>
  <?php if ($p['id']): ?><a class="btn btn--ghost" href="/blog/<?= e($p['slug']) ?><?= empty($p['published']) ? '?apercu=1' : '' ?>" target="_blank" rel="noopener"><?= empty($p['published']) ? 'Aperçu' : 'Voir l’article' ?> ↗</a><?php endif; ?>
</div>
<form method="post" class="post-form" data-post-form>
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <div class="post-form__main card">
    <label class="title-field"><span class="sr-only">Titre</span><input name="title" required value="<?= e($p['title']) ?>" placeholder="Titre de l’article"></label>
    <label>Chapô (extrait affiché dans la liste)<textarea name="excerpt" rows="2" maxlength="400"><?= e($p['excerpt']) ?></textarea></label>
    <div class="rte" data-rte>
      <div class="rte__bar" role="toolbar" aria-label="Mise en forme">
        <button type="button" data-cmd="formatBlock" data-val="h2" title="Titre de section">H2</button>
        <button type="button" data-cmd="formatBlock" data-val="h3" title="Sous-titre">H3</button>
        <button type="button" data-cmd="formatBlock" data-val="p" title="Paragraphe">¶</button>
        <button type="button" data-cmd="bold" title="Gras"><b>G</b></button>
        <button type="button" data-cmd="italic" title="Italique"><i>I</i></button>
        <button type="button" data-cmd="insertUnorderedList" title="Liste à puces">• Liste</button>
        <button type="button" data-cmd="insertOrderedList" title="Liste numérotée">1. Liste</button>
        <button type="button" data-cmd="formatBlock" data-val="blockquote" title="Citation">“ ”</button>
        <button type="button" data-cmd="link" title="Lien">Lien</button>
        <button type="button" data-cmd="image" title="Image">Image</button>
        <button type="button" data-cmd="removeFormat" title="Effacer la mise en forme">Effacer</button>
        <button type="button" data-cmd="source" title="Code HTML">&lt;/&gt;</button>
      </div>
      <div class="rte__area prose-admin" contenteditable="true" data-rte-area><?= $p['content'] ?></div>
      <textarea class="rte__src" name="content" data-rte-src hidden><?= e($p['content']) ?></textarea>
    </div>
  </div>
  <aside class="post-form__side">
    <div class="card form">
      <label class="check"><input type="checkbox" name="published" value="1"<?= !empty($p['published']) ? ' checked' : '' ?>> Publié</label>
      <label>Date de publication<input type="date" name="date" value="<?= e(substr((string) $p['date'], 0, 10)) ?>"></label>
      <label>Thème<select name="theme"><?php foreach ($themes as $t): ?><option<?= $p['theme'] === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></label>
      <div><span class="lbl">Image de couverture</span><div class="imgfield" data-imgfield><input type="hidden" name="image" value="<?= e($p['image']) ?>"></div></div>
      <button class="btn btn--red btn--block" type="submit">Enregistrer</button>
    </div>
    <?php if ($p['id']): $gen = array_values((array) ($p['ai']['images'] ?? [])); ?>
      <div class="card form ph" id="photo" data-photo-ai data-id="<?= e($p['id']) ?>" data-images="<?= e(json_encode($gen)) ?>">
        <h3 class="h3">✨ Nouvelle photo par l’IA</h3>
        <?php if (!$canImage): ?>
          <p class="small muted">Ajoute ta clé API Google Gemini dans <a href="/admin/ia">IA & clés API</a> pour générer la photo avec Nano Banana.</p>
        <?php else: ?>
          <label>Décris la photo que tu veux <small class="muted">(facultatif)</small>
            <textarea rows="4" maxlength="1500" data-ph-prompt placeholder="Ex. : gros plan sur les mains d’une boulangère qui encaisse avec un terminal de paiement, devanture en bois, lumière du matin"></textarea>
          </label>
          <p class="small muted">Laissé vide, l’IA repart du contenu de l’article avec un autre cadrage, une autre lumière et d’autres couleurs que les dernières photos du blog.</p>
          <button class="btn btn--dark btn--block" type="button" data-ph-go>✨ Générer une nouvelle photo</button>
          <p class="ph__status small" data-ph-status hidden></p>
          <div data-ph-hist></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="card form">
      <h3 class="h3">Référencement</h3>
      <label>Adresse (slug)<input name="slug" value="<?= e($p['slug']) ?>" placeholder="genere-depuis-le-titre"></label>
      <label>Titre SEO<input name="seo_title" maxlength="160" value="<?= e($p['seo_title']) ?>" placeholder="Par défaut : le titre"></label>
      <label>Description SEO<textarea name="seo_description" rows="3" maxlength="320" placeholder="Par défaut : le chapô"><?= e($p['seo_description']) ?></textarea></label>
    </div>
  </aside>
</form>
<?php if ($p['id']): ?>
  <form method="post" action="/admin/blog/<?= e($p['id']) ?>/supprimer" class="danger" data-confirm="Supprimer définitivement cet article ?">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="link-danger" type="submit">Supprimer l’article</button>
  </form>
<?php endif; ?>
