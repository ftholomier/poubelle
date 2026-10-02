<?php
use App\Core\Url;
/** @var array $cats @var array $counts */
$rows = array_values($cats);
$rows[] = ['slug' => '', 'name' => '', 'one' => '', 'many' => '', 'emoji' => '', 'color' => '#ffd23f', 'visible' => true, 'tagline' => '', 'intro' => '', 'keywords' => [], 'order' => count($rows)];
?>
<div class="adm-head"><div><h1>Métiers</h1><p>Catégories de l'annuaire : noms, couleurs (repères de la carte), mots-clés du classement automatique, texte d'introduction des pages. Changer l'adresse (slug) d'un métier crée de nouvelles URL : à éviter une fois le site indexé.</p></div></div>
<form class="form" method="post" action="<?= e(Url::admin('categories')) ?>" data-dirty-check>
  <?= csrf_field() ?>
  <?php foreach ($rows as $i => $c): ?>
    <details class="box"<?= $c['slug'] === '' ? '' : '' ?>>
      <summary><span style="display:inline-grid;place-items:center;width:30px;height:30px;border-radius:50%;border:2px solid var(--ink);background:<?= e((string) $c['color']) ?>;margin-right:8px"><?= e((string) ($c['emoji'] ?? '')) ?></span><b><?= e($c['name'] !== '' ? $c['name'] : '+ Ajouter un métier') ?></b> <span class="muted small"><?= $c['slug'] !== '' ? '/' . e($c['slug']) . '/ · ' . nf($counts[$c['slug']] ?? 0) . ' pros en ligne' . (empty($c['visible']) ? ' · masqué' : '') : '' ?></span></summary>
      <div class="form-grid mt-2">
        <div class="field"><label>Nom</label><input type="text" name="cats[<?= $i ?>][name]" maxlength="60" value="<?= e((string) $c['name']) ?>"></div>
        <div class="field"><label>Adresse (slug)</label><input type="text" name="cats[<?= $i ?>][slug]" maxlength="50" value="<?= e((string) $c['slug']) ?>"></div>
        <div class="field"><label>Au singulier (« un … »)</label><input type="text" name="cats[<?= $i ?>][one]" value="<?= e((string) ($c['one'] ?? '')) ?>"></div>
        <div class="field"><label>Au pluriel</label><input type="text" name="cats[<?= $i ?>][many]" value="<?= e((string) ($c['many'] ?? '')) ?>"></div>
        <div class="field"><label>Emoji</label><input type="text" name="cats[<?= $i ?>][emoji]" maxlength="8" value="<?= e((string) ($c['emoji'] ?? '')) ?>"></div>
        <div class="field"><label>Couleur</label><input type="color" name="cats[<?= $i ?>][color]" value="<?= e((string) $c['color']) ?>" style="min-height:44px;padding:4px"></div>
        <div class="field"><label>Ordre</label><input type="number" name="cats[<?= $i ?>][order]" value="<?= (int) ($c['order'] ?? $i) ?>"></div>
      </div>
      <div class="field"><label>Accroche</label><input type="text" name="cats[<?= $i ?>][tagline]" maxlength="200" value="<?= e((string) ($c['tagline'] ?? '')) ?>"></div>
      <div class="field"><label>Texte d'introduction de la page (facultatif, sinon texte automatique)</label><textarea name="cats[<?= $i ?>][intro]" rows="3"><?= e((string) ($c['intro'] ?? '')) ?></textarea></div>
      <div class="field"><label>Mots-clés du classement automatique (séparés par des virgules)</label><textarea name="cats[<?= $i ?>][keywords]" rows="2" class="code"><?= e(implode(', ', (array) ($c['keywords'] ?? []))) ?></textarea></div>
      <label class="switch"><input type="checkbox" name="cats[<?= $i ?>][visible]" value="1"<?= !empty($c['visible']) ? ' checked' : '' ?>> Visible sur le site</label>
    </details>
  <?php endforeach; ?>
  <div class="form-actions"><button class="btn btn-coral" type="submit">Enregistrer les métiers</button></div>
</form>
