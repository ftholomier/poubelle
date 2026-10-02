<?php
use App\Core\Url;
use App\Services\Categories;
/** @var array $items */
$items[] = ['key' => '', 'slug' => '', 'name' => '', 'title' => '', 'emoji' => '', 'bg' => '#fff6e8', 'fg' => '#1c1233', 'tilt' => 0, 'hint' => '', 'keywords' => [], 'cats' => [], 'intro' => ''];
?>
<div class="adm-head"><div><h1>Occasions</h1><p>Cartes « Quelle est l'occasion ? » de l'accueil et pages /animation-mariage/, etc.</p></div></div>
<form class="form" method="post" action="<?= e(Url::admin('occasions')) ?>" data-dirty-check>
  <?= csrf_field() ?>
  <?php foreach ($items as $i => $o): ?>
    <details class="box">
      <summary><b><?= e(($o['emoji'] ?? '') . ' ' . ($o['name'] !== '' ? $o['name'] : '+ Ajouter une occasion')) ?></b> <span class="muted small"><?= $o['slug'] !== '' ? '/' . e($o['slug']) . '/' : '' ?></span></summary>
      <div class="form-grid mt-2">
        <div class="field"><label>Nom</label><input type="text" name="occ[<?= $i ?>][name]" value="<?= e((string) $o['name']) ?>"></div>
        <div class="field"><label>Adresse (slug)</label><input type="text" name="occ[<?= $i ?>][slug]" value="<?= e((string) $o['slug']) ?>"></div>
        <div class="field"><label>Clé interne</label><input type="text" name="occ[<?= $i ?>][key]" value="<?= e((string) $o['key']) ?>"></div>
        <div class="field"><label>Emoji</label><input type="text" name="occ[<?= $i ?>][emoji]" maxlength="8" value="<?= e((string) $o['emoji']) ?>"></div>
        <div class="field"><label>Fond</label><input type="color" name="occ[<?= $i ?>][bg]" value="<?= e((string) $o['bg']) ?>" style="min-height:44px;padding:4px"></div>
        <div class="field"><label>Texte</label><input type="color" name="occ[<?= $i ?>][fg]" value="<?= e((string) $o['fg']) ?>" style="min-height:44px;padding:4px"></div>
        <div class="field"><label>Inclinaison (°)</label><input type="number" step="0.1" min="-4" max="4" name="occ[<?= $i ?>][tilt]" value="<?= e((string) $o['tilt']) ?>"></div>
      </div>
      <div class="form-grid"><div class="field"><label>Titre de la page</label><input type="text" name="occ[<?= $i ?>][title]" value="<?= e((string) $o['title']) ?>"></div><div class="field"><label>Indication sur la carte</label><input type="text" name="occ[<?= $i ?>][hint]" value="<?= e((string) $o['hint']) ?>"></div></div>
      <div class="field"><label>Métiers associés</label><select name="occ[<?= $i ?>][cats][]" multiple data-multi aria-label="Métiers associés"><?php foreach (Categories::all(true) as $s => $c): ?><option value="<?= e($s) ?>"<?= in_array($s, (array) $o['cats'], true) ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Mots-clés (détection dans les demandes)</label><input type="text" name="occ[<?= $i ?>][keywords]" value="<?= e(implode(', ', (array) $o['keywords'])) ?>"></div>
      <div class="field"><label>Texte d'introduction (facultatif)</label><textarea name="occ[<?= $i ?>][intro]" rows="3"><?= e((string) ($o['intro'] ?? '')) ?></textarea></div>
    </details>
  <?php endforeach; ?>
  <div class="form-actions"><button class="btn btn-coral" type="submit">Enregistrer les occasions</button></div>
</form>
