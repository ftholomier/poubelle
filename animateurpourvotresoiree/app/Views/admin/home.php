<?php
use App\Core\Url;
/** @var array $h */
$v = static fn (string $k) => e((string) ($h[$k] ?? ''));
$field = static fn (string $k, string $label, string $hint = '', int $max = 200) => '<div class="field"><label for="h-' . $k . '">' . e($label) . '</label><input id="h-' . $k . '" type="text" name="' . $k . '" maxlength="' . $max . '" value="' . e((string) ($h[$k] ?? '')) . '">' . ($hint !== '' ? '<span class="hint">' . e($hint) . '</span>' : '') . '</div>';
?>
<div class="adm-head"><div><h1>Page <span class="serif">d'accueil</span></h1><p>Textes, images et blocs de la page d'accueil. Variables : <code>{nb}</code> (nombre de pros en ligne), <code>{demandes}</code> (demandes depuis 2003).</p></div><a class="btn btn-sm" href="/" target="_blank" rel="noopener"><?= icon('eye', 16) ?> Voir</a></div>
<form class="form" method="post" action="<?= e(Url::admin('accueil')) ?>" enctype="multipart/form-data" data-dirty-check>
  <?= csrf_field() ?>
  <div class="box">
    <h2>En-tête (hero)</h2>
    <?= $field('badge', 'Badge', 'Ex. : {nb} pros prêts à mettre le feu') ?>
    <div class="form-grid"><?= $field('title_1', 'Titre — ligne 1') ?><?= $field('title_2', 'Titre — début ligne 2') ?><?= $field('title_em', 'Titre — mot en italique corail') ?><?= $field('title_3', 'Titre — fin') ?></div>
    <div class="field"><label for="h-subtitle">Sous-titre</label><textarea id="h-subtitle" name="subtitle" rows="2" maxlength="400"><?= $v('subtitle') ?></textarea></div>
    <div class="form-grid">
      <?php foreach ([1, 2] as $n): ?>
        <div class="field"><label>Image <?= $n ?></label>
          <?php if (!empty($h['hero_image_' . $n])): ?><img src="<?= e((string) $h['hero_image_' . $n]) ?>" alt="" style="max-height:120px;border:2px solid var(--ink);border-radius:12px"><label class="check small"><input type="checkbox" name="hero_image_<?= $n ?>_remove" value="1"> Revenir à l'illustration par défaut</label><?php endif; ?>
          <input type="file" name="hero_image_<?= $n ?>" accept="image/jpeg,image/png,image/webp" class="small">
          <input type="text" name="hero_image_<?= $n ?>_alt" maxlength="160" value="<?= $v('hero_image_' . $n . '_alt') ?>" placeholder="Texte alternatif" class="input"></div>
      <?php endforeach; ?>
    </div>
    <label class="switch"><input type="checkbox" name="sticker_show" value="1"<?= !empty($h['sticker_show']) ? ' checked' : '' ?>> Afficher la pastille</label>
    <div class="form-grid"><?= $field('sticker_1', 'Pastille — ligne 1') ?><?= $field('sticker_2', 'Pastille — ligne 2') ?><?= $field('sticker_3', 'Pastille — ligne 3') ?></div>
    <label class="switch"><input type="checkbox" name="testimonial_use_reviews" value="1"<?= !empty($h['testimonial_use_reviews']) ? ' checked' : '' ?>> Afficher un vrai avis client récent (sinon le texte ci-dessous)</label>
    <div class="form-grid"><?= $field('testimonial_text', 'Témoignage par défaut') ?><?= $field('testimonial_author', 'Signature') ?></div>
    <fieldset class="field"><legend class="label">Recherches populaires (liens sous la recherche)</legend>
      <?php $pop = array_values((array) ($h['popular'] ?? [])); for ($i = 0; $i < 6; $i++): ?>
        <div class="form-grid"><input class="input" type="text" name="popular[<?= $i ?>][label]" value="<?= e((string) ($pop[$i]['label'] ?? '')) ?>" placeholder="Libellé"><input class="input" type="text" name="popular[<?= $i ?>][url]" value="<?= e((string) ($pop[$i]['url'] ?? '')) ?>" placeholder="/dj/"></div>
      <?php endfor; ?>
    </fieldset>
  </div>
  <div class="box">
    <h2>Bandeau défilant</h2>
    <div class="field"><label for="h-ticker">Mots (un par ligne)</label><textarea id="h-ticker" name="ticker" rows="4"><?= e(implode("\n", (array) ($h['ticker'] ?? []))) ?></textarea></div>
  </div>
  <div class="box">
    <h2>Sections</h2>
    <div class="form-grid"><?= $field('pros_title', 'Titre « pros »') ?><?= $field('pros_title_em', '… en italique') ?><div class="field"><label for="h-fc">Nombre de pros affichés</label><input id="h-fc" type="number" name="featured_count" min="4" max="24" value="<?= (int) ($h['featured_count'] ?? 8) ?>"></div></div>
    <div class="form-grid"><?= $field('events_title', 'Titre « occasions »') ?><?= $field('events_title_em', '… en italique') ?></div>
    <div class="form-grid"><?= $field('how_title', 'Titre « étapes »') ?><?= $field('how_title_em', '… en italique') ?></div>
    <?php $steps = array_values((array) ($h['steps'] ?? [])); for ($i = 0; $i < 3; $i++): ?>
      <div class="form-grid"><input class="input" type="text" name="steps[<?= $i ?>][t]" value="<?= e((string) ($steps[$i]['t'] ?? '')) ?>" placeholder="Étape <?= $i + 1 ?> — titre"><input class="input" type="text" name="steps[<?= $i ?>][d]" value="<?= e((string) ($steps[$i]['d'] ?? '')) ?>" placeholder="Description"></div>
    <?php endfor; ?>
  </div>
  <div class="box">
    <h2>Bloc « Pour les pros »</h2>
    <div class="form-grid"><?= $field('join_kicker', 'Sur-titre') ?><?= $field('join_title', 'Titre') ?><?= $field('join_title_em', '… en italique') ?></div>
    <div class="field"><label for="h-join">Texte</label><textarea id="h-join" name="join_text" rows="2"><?= $v('join_text') ?></textarea></div>
    <div class="form-grid"><?= $field('stat1_value', 'Chiffre 1') ?><?= $field('stat1_label', 'Légende 1') ?><?= $field('stat2_value', 'Chiffre 2') ?><?= $field('stat2_label', 'Légende 2') ?></div>
    <label class="switch"><input type="checkbox" name="local_links" value="1"<?= !empty($h['local_links']) ? ' checked' : '' ?>> Afficher les liens locaux (villes, départements) en bas de page</label>
  </div>
  <div class="form-actions"><button class="btn btn-coral" type="submit">Enregistrer la page d'accueil</button></div>
</form>
