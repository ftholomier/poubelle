<?php
/** L'actu : création et édition des articles. */

use App\Admin;
use App\Config;
use App\Csrf;
use App\Router;
use App\Text;
use App\View;

echo View::admin('_layout_start', get_defined_vars());

$editingPost = null;
foreach ($posts as $post) {
    if ((string) ($post['slug'] ?? '') === $editing && $editing !== '') {
        $editingPost = $post;
    }
}
$showForm = $creating || $editingPost !== null;
?>
<div class="screen">
  <?php if ($showForm):
      $p = $editingPost ?? [];
      $en = (array) ($p['i18n']['en'] ?? []); ?>
    <section class="panel" style="margin-top:24px">
      <div class="panel__head">
        <h2><?= $editingPost === null ? 'Nouvel article' : 'Modifier l’article' ?></h2>
        <a class="link-underline" href="<?= Text::e(Router::adminUrl('posts')) ?>">Annuler</a>
      </div>
      <form class="panel__body" method="post" action="<?= Text::e(Router::adminUrl()) ?>">
        <?= Csrf::field('admin') ?>
        <input type="hidden" name="action" value="post-save">
        <input type="hidden" name="original" value="<?= Text::e((string) ($p['slug'] ?? '')) ?>">

        <label class="label" for="p-title">TITRE</label>
        <input class="title-input" id="p-title" type="text" name="p[title]" required value="<?= Text::e((string) ($p['title'] ?? '')) ?>">

        <div class="grid-3" style="margin-top:18px">
          <div>
            <label class="label" for="p-date">DATE</label>
            <input class="field" id="p-date" type="date" name="p[date]" value="<?= Text::e((string) ($p['date'] ?? date('Y-m-d'))) ?>">
          </div>
          <div>
            <label class="label" for="p-slug">ADRESSE (SLUG)</label>
            <input class="field" id="p-slug" type="text" name="p[slug]" value="<?= Text::e((string) ($p['slug'] ?? '')) ?>" placeholder="généré depuis le titre">
          </div>
          <div>
            <label class="label" for="p-status">ÉTAT</label>
            <select class="field" id="p-status" name="p[status]">
              <option value="published" <?= ($p['status'] ?? 'draft') === 'published' ? 'selected' : '' ?>>Publié</option>
              <option value="draft" <?= ($p['status'] ?? 'draft') !== 'published' ? 'selected' : '' ?>>Brouillon</option>
            </select>
          </div>
        </div>

        <label class="label" style="margin-top:18px" for="p-excerpt">CHAPEAU</label>
        <textarea class="field" id="p-excerpt" name="p[excerpt]" rows="2"><?= Text::e((string) ($p['excerpt'] ?? '')) ?></textarea>

        <label class="label" style="margin-top:18px">CORPS · WYSIWYG</label>
        <?= View::admin('_wysiwyg', ['name' => 'p[body]', 'value' => (string) ($p['body'] ?? ''), 'id' => 'p-body']) ?>

        <?= View::admin('_field', [
            'field' => ['label' => 'Image de l’article', 'type' => 'media', 'path' => 'image', 'max' => 1],
            'value' => (string) ($p['image'] ?? ''),
            'name' => 'p[image]',
            'media' => $media,
        ]) ?>

        <label class="label" style="margin-top:18px" for="p-color">COULEUR DE VIGNETTE</label>
        <div style="display:flex;gap:10px;align-items:center">
          <input class="field" id="p-color" type="text" name="p[color]" value="<?= Text::e((string) ($p['color'] ?? '#FFD100')) ?>" style="max-width:160px" data-color-text>
          <input type="color" value="<?= Text::e(preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($p['color'] ?? '')) === 1 ? (string) $p['color'] : '#FFD100') ?>" data-color-picker aria-label="Couleur" style="width:52px;height:52px;border:2px solid #0E0E0E;border-radius:12px;background:none;cursor:pointer;padding:2px">
        </div>

        <h3 style="margin:26px 0 0;font:800 17px/1 'Bricolage Grotesque',sans-serif;padding-top:18px;border-top:2px solid rgba(14,14,14,.12)">Version anglaise</h3>
        <label class="label" style="margin-top:14px" for="p-entitle">TITRE (EN)</label>
        <input class="field" id="p-entitle" type="text" name="p[en_title]" value="<?= Text::e((string) ($en['title'] ?? '')) ?>">
        <label class="label" style="margin-top:18px" for="p-enexcerpt">CHAPEAU (EN)</label>
        <textarea class="field" id="p-enexcerpt" name="p[en_excerpt]" rows="2"><?= Text::e((string) ($en['excerpt'] ?? '')) ?></textarea>
        <label class="label" style="margin-top:18px">CORPS (EN)</label>
        <?= View::admin('_wysiwyg', ['name' => 'p[en_body]', 'value' => (string) ($en['body'] ?? ''), 'id' => 'p-enbody']) ?>

        <div class="form-actions">
          <button class="btn btn--ink" type="submit"><?= $editingPost === null ? 'Créer l’article' : 'Enregistrer' ?></button>
          <a class="btn btn--outline" href="<?= Text::e(Router::adminUrl('posts')) ?>">Annuler</a>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <section class="panel" style="margin-top:<?= $showForm ? '18' : '24' ?>px">
    <div class="panel__head">
      <h2><?= \count($posts) ?> article(s)</h2>
      <a class="btn btn--ink btn--sm" href="<?= Text::e(Router::adminUrl('posts', ['new' => 1])) ?>">+ Nouvel article</a>
    </div>
    <div class="panel__scroll">
      <?php if ($posts === []): ?>
        <div class="panel__body muted">Aucun article. La page « L'actu » affichera un message d'attente.</div>
      <?php else: ?>
        <?php foreach ($posts as $post):
            $slug = (string) ($post['slug'] ?? ''); ?>
          <div class="row row--media">
            <?php if (!empty($post['image'])): ?>
              <img class="thumb" src="<?= Text::e(Config::basePath() . (string) $post['image']) ?>" alt="" loading="lazy">
            <?php else: ?>
              <span class="thumb" style="background:<?= Text::e((string) ($post['color'] ?? '#FFD100')) ?>"></span>
            <?php endif; ?>
            <div>
              <div class="row__title"><?= Text::e((string) ($post['title'] ?? '')) ?></div>
              <div class="row__sub"><?= Text::e((string) ($post['date'] ?? '')) ?> · /<?= Text::e($slug) ?></div>
            </div>
            <span class="badge" style="background:<?= ($post['status'] ?? '') === 'published' ? '#12B39A' : '#EDE5D5' ?>"><?= ($post['status'] ?? '') === 'published' ? 'PUBLIÉ' : 'BROUILLON' ?></span>
            <div class="row__actions">
              <a class="btn btn--sm btn--outline" href="<?= Text::e(Router::adminUrl('posts', ['edit' => $slug])) ?>">Modifier</a>
              <form method="post" action="<?= Text::e(Router::adminUrl()) ?>" data-confirm="Supprimer cet article ?">
                <?= Csrf::field('admin') ?>
                <input type="hidden" name="action" value="post-delete">
                <input type="hidden" name="slug" value="<?= Text::e($slug) ?>">
                <button class="btn btn--sm btn--danger" type="submit">Supprimer</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>
<?= View::admin('_layout_end') ?>
