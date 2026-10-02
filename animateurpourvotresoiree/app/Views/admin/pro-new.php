<?php
use App\Core\Url;
use App\Services\Categories;
?>
<p><a class="link small" href="<?= e(Url::admin('pros')) ?>">← Pros</a></p>
<div class="adm-head"><div><h1>Nouveau <span class="serif">pro</span></h1><p>Création manuelle d'une fiche (vous pourrez la compléter ensuite).</p></div></div>
<form class="box form" method="post" action="<?= e(Url::admin('pros/nouveau')) ?>" style="max-width:860px">
  <?= csrf_field() ?>
  <div class="form-grid">
    <div class="field"><label for="n-name">Nom affiché *</label><input id="n-name" type="text" name="display_name" required maxlength="80" value="<?= e(old('display_name')) ?>"></div>
    <div class="field autocomplete"><label for="n-city">Ville *</label><input id="n-city" type="text" name="city" autocomplete="off" data-commune-input required value="<?= e(old('city')) ?>"><input type="hidden" name="insee" data-commune-insee value="<?= e(old('insee')) ?>"></div>
    <div class="field"><label for="n-fn">Prénom</label><input id="n-fn" type="text" name="first_name" maxlength="60" value="<?= e(old('first_name')) ?>"></div>
    <div class="field"><label for="n-ln">Nom</label><input id="n-ln" type="text" name="last_name" maxlength="60" value="<?= e(old('last_name')) ?>"></div>
    <div class="field"><label for="n-em">Email</label><input id="n-em" type="email" name="email" value="<?= e(old('email')) ?>"></div>
    <div class="field"><label for="n-ph">Téléphone</label><input id="n-ph" type="tel" name="phone" value="<?= e(old('phone')) ?>"></div>
  </div>
  <div class="field"><label for="n-tag">Accroche</label><input id="n-tag" type="text" name="tagline" maxlength="220" value="<?= e(old('tagline')) ?>"></div>
  <fieldset class="field"><legend class="label">Métiers *</legend><div class="choice-grid"><?php foreach (Categories::all(true) as $s => $c): ?><label class="choice"><input type="checkbox" name="categories[]" value="<?= e($s) ?>"><span><?= e($c['emoji'] . ' ' . $c['name']) ?></span></label><?php endforeach; ?></div></fieldset>
  <div class="row-wrap">
    <label class="switch"><input type="checkbox" name="status" value="pending"> Créer « à valider » (non publiée)</label>
    <label class="switch"><input type="checkbox" name="invite" value="1" checked> Envoyer au pro un lien pour activer son espace</label>
  </div>
  <div class="form-actions"><button class="btn btn-coral" type="submit">Créer la fiche</button></div>
</form>
