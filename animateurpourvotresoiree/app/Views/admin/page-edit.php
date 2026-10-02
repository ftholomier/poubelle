<?php
use App\Core\Url;
use App\Services\Pages;
/** @var ?array $p */
$id = (int) ($p['id'] ?? 0);
$reserved = $p && in_array($p['slug'], Pages::RESERVED, true);
?>
<p><a class="link small" href="<?= e(Url::admin('pages')) ?>">← Pages</a></p>
<div class="adm-head"><div><h1><?= e($p['title'] ?? 'Nouvelle page') ?></h1><?php if ($p): ?><p><a href="<?= e(Url::page($p['slug'])) ?>" target="_blank" rel="noopener">Voir la page ↗</a></p><?php endif; ?></div>
  <?php if ($p && !$reserved): ?><form method="post" action="<?= e(Url::admin('pages/' . $id)) ?>" data-confirm="Supprimer cette page ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm" type="submit">Supprimer</button></form><?php endif; ?></div>
<form class="form" method="post" action="<?= e(Url::admin($p ? 'pages/' . $id : 'pages/nouvelle')) ?>" data-dirty-check>
  <?= csrf_field() ?>
  <div class="box">
    <div class="form-grid">
      <div class="field"><label for="pg-title">Titre *</label><input id="pg-title" type="text" name="title" maxlength="160" required value="<?= e((string) ($p['title'] ?? '')) ?>"></div>
      <div class="field"><label for="pg-slug">Adresse /…/</label><input id="pg-slug" type="text" name="slug" maxlength="60" value="<?= e((string) ($p['slug'] ?? '')) ?>" data-slug-from="#pg-title"<?= $reserved ? ' readonly' : '' ?>></div>
      <div class="field"><label for="pg-status">Statut</label><select id="pg-status" name="status"><option value="published"<?= ($p['status'] ?? 'published') === 'published' ? ' selected' : '' ?>>Publiée</option><option value="draft"<?= ($p['status'] ?? '') === 'draft' ? ' selected' : '' ?>>Brouillon</option></select></div>
    </div>
    <div class="field"><label for="pg-body">Contenu</label><textarea id="pg-body" name="body" data-editor="full" data-upload="<?= e(Url::admin('upload')) ?>" rows="20"><?= e((string) ($p['body'] ?? '')) ?></textarea></div>
    <div class="form-grid">
      <div class="field"><label for="pg-st">Titre SEO</label><input id="pg-st" type="text" name="seo_title" maxlength="70" value="<?= e((string) ($p['seo']['title'] ?? '')) ?>" data-seo-count="60"></div>
      <div class="field"><label for="pg-sd">Meta description</label><input id="pg-sd" type="text" name="seo_description" maxlength="170" value="<?= e((string) ($p['seo']['description'] ?? '')) ?>" data-seo-count="160"></div>
    </div>
    <label class="switch"><input type="checkbox" name="in_footer" value="1"<?= !empty($p['in_footer']) ? ' checked' : '' ?>> Lien dans le pied de page</label>
  </div>
  <div class="form-actions"><button class="btn btn-coral" type="submit">Enregistrer la page</button></div>
</form>
