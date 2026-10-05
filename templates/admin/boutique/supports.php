<?php
/**
 * Boutique › Supports : produits vierges de l'imprimeur. Variables : $supports, $mockups
 */
$form = function (array $s, bool $new = false) use ($mockups): string {
    ob_start(); ?>
  <form method="post" action="/admin/boutique/supports" class="card card--pad" id="s-<?= e($s['key'] ?: 'nouveau') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="key" value="<?= e($s['key']) ?>">
    <div class="card__head"><h2 class="card__t"><?= $new ? 'Nouveau support' : e($s['name']) ?></h2><?php if (!$new): ?><span class="card__note"><?= $s['active'] ? 'actif' : 'désactivé' ?><?= $s['custom'] ? ' · ajouté' : '' ?></span><?php endif; ?></div>
    <div class="fgrid fgrid--2">
      <label class="f"><span class="f__k">Nom</span><input class="in" name="name" value="<?= e($s['name']) ?>" required></label>
      <label class="f"><span class="f__k">Aperçu</span><select class="in" name="mockup"><?php foreach ($mockups as $k => $l): ?><option value="<?= e($k) ?>"<?= $s['mockup'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <label class="f"><span class="f__k">Référence chez l’imprimeur</span><input class="in" name="ref" value="<?= e($s['ref']) ?>" placeholder="ex. TS-BIO-150"></label>
      <label class="f"><span class="f__k">Tailles (séparées par des virgules)</span><input class="in" name="sizes" value="<?= e(implode(', ', $s['sizes'])) ?>" placeholder="S, M, L, XL"></label>
    </div>
    <div class="f"><span class="f__k">Faces imprimables (mm)</span>
      <table class="xs" style="width:100%"><thead><tr><th style="text-align:left">Libellé</th><th>Largeur</th><th>Hauteur</th><th>Fonds perdus</th></tr></thead><tbody>
      <?php $faces = array_values($s['faces']); for ($i = 0; $i < max(2, count($faces) + 1); $i++): $f = $faces[$i] ?? ['label' => '', 'w' => '', 'h' => '', 'bleed' => '']; ?>
        <tr><td><input class="in in--sm" name="faces[<?= $i ?>][label]" value="<?= e((string) $f['label']) ?>" placeholder="<?= $i ? 'Dos' : 'Avant' ?>"></td><td><input class="in in--sm" type="number" step="0.5" name="faces[<?= $i ?>][w]" value="<?= e((string) $f['w']) ?>"></td><td><input class="in in--sm" type="number" step="0.5" name="faces[<?= $i ?>][h]" value="<?= e((string) $f['h']) ?>"></td><td><input class="in in--sm" type="number" step="0.5" name="faces[<?= $i ?>][bleed]" value="<?= e((string) $f['bleed']) ?>"></td></tr>
      <?php endfor; ?>
      </tbody></table>
    </div>
    <label class="f"><span class="f__k">Couleurs du produit vierge (une par ligne : « Nom : #RRGGBB » ; vide : imprimé en entier, le fond fait partie du dessin)</span>
      <textarea class="in" name="colors" rows="3"><?= e(implode("\n", array_map(fn ($n, $h) => "$n : $h", array_keys($s['colors']), $s['colors']))) ?></textarea></label>
    <label class="f"><span class="f__k">Consignes de l’imprimeur</span><input class="in" name="note" value="<?= e($s['note']) ?>"></label>
    <div class="row" style="justify-content:space-between;align-items:center">
      <label class="toggle"><input type="checkbox" name="active" value="1"<?= $s['active'] ? ' checked' : '' ?>><span class="toggle__box"></span><span>Proposé pour de nouveaux modèles</span></label>
      <button class="btn btn--navy btn--sm"><?= $new ? 'Ajouter le support' : 'Enregistrer' ?></button>
    </div>
  </form>
    <?php return (string) ob_get_clean();
};
?>
<p class="small" style="margin:0 0 12px;max-width:95ch">Les produits vierges de l’imprimeur : dimensions de chaque face imprimable (en millimètres, format fini), fonds perdus (marge de sécurité coupée après impression : 2 à 3 mm pour le papier), couleurs disponibles et tailles. Les modèles se dessinent dessus.</p>
<div class="cols" style="align-items:start;grid-template-columns:repeat(auto-fill,minmax(420px,1fr))">
  <?php foreach ($supports as $s): ?><?= $form($s) ?><?php endforeach; ?>
  <?= $form(['key' => '', 'name' => '', 'mockup' => 'paper', 'faces' => [], 'colors' => [], 'sizes' => [], 'ref' => '', 'note' => '', 'active' => true, 'custom' => true], true) ?>
</div>
