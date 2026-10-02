<?php
use App\Core\Url;
/** @var array $seo @var array $types @var array $examples */
?>
<div class="adm-head"><div><h1>Titres <span class="serif">& descriptions</span></h1><p>Gabarits utilisés pour générer automatiquement les balises title et meta description de toutes les pages, avec mots-clés + localisation.</p></div></div>
<div class="box mb-2">
  <h2>Variables disponibles</h2>
  <p class="small"><code>{Many}</code> métier au pluriel avec majuscule (« DJ », « Magiciens ») · <code>{many}</code> au pluriel · <code>{one}</code> au singulier · <code>{an_one}</code> « un magicien » · <code>{in}</code> « dans le Rhône », « à Lyon », « en Bretagne » · <code>{code}</code> n° de département · <code>{nb}</code> nombre de pros · <code>{occasion}</code> · fiche : <code>{name}</code>, <code>{cat}</code>/<code>{Cat}</code>, <code>{in_city}</code>, <code>{tagline}</code> · <code>{site}</code>.</p>
</div>
<form class="form" method="post" action="<?= e(Url::admin('seo')) ?>" enctype="multipart/form-data" data-dirty-check>
  <?= csrf_field() ?>
  <?php foreach ($types as $t => [$label, $example]): ?>
    <div class="box">
      <div class="box-head"><h2><?= e($label) ?></h2><?php if ($example !== ''): ?><span class="small muted">ex. <?= e($example) ?></span><?php endif; ?></div>
      <div class="field"><label for="seo-<?= $t ?>-t">Titre</label><input id="seo-<?= $t ?>-t" type="text" name="<?= $t ?>[title]" maxlength="200" value="<?= e((string) ($seo[$t]['title'] ?? '')) ?>"></div>
      <div class="field"><label for="seo-<?= $t ?>-d">Description</label><textarea id="seo-<?= $t ?>-d" name="<?= $t ?>[description]" rows="2" maxlength="400"><?= e((string) ($seo[$t]['description'] ?? '')) ?></textarea></div>
      <div class="serp"><div class="u">animateurpourvotresoiree.com<?= e($example) ?></div><div class="t"><?= e(App\Services\Seo::title($examples[$t]['title'])) ?></div><div class="d"><?= e($examples[$t]['description']) ?></div></div>
    </div>
  <?php endforeach; ?>
  <div class="box">
    <h2>Réglages généraux</h2>
    <div class="form-grid">
      <div class="field"><label for="seo-suffix">Suffixe de marque des titres</label><input id="seo-suffix" type="text" name="suffix" maxlength="60" value="<?= e((string) ($seo['suffix'] ?? '')) ?>"><span class="hint">Ajouté seulement si le titre reste sous 70 caractères.</span></div>
      <div class="field"><label>Image de partage (réseaux sociaux, 1200×630)</label><?php if (!empty($seo['og_image'])): ?><img src="<?= e((string) $seo['og_image']) ?>" alt="" style="max-height:90px;border:2px solid var(--ink);border-radius:10px"><?php endif; ?><input type="file" name="og_image" accept="image/jpeg,image/png,image/webp" class="small"></div>
    </div>
    <label class="switch"><input type="checkbox" name="noindex_empty" value="1"<?= !empty($seo['noindex_empty']) ? ' checked' : '' ?>> Ne pas indexer les pages locales sans aucun pro (évite le contenu vide)</label>
  </div>
  <div class="form-actions"><button class="btn btn-coral" type="submit">Enregistrer</button></div>
</form>
