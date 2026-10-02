<?php
use App\Services\Categories;
use App\Services\Geo;

/** @var array $pro @var ?array $commune @var bool $aiOn @var int $maxZones */
$v = static fn (string $k, mixed $d = '') => old($k, $pro[$k] ?? $d);
$cats = (array) old('categories', $pro['categories'] ?? []);
$zones = (array) old('zones', $pro['zones'] ?? []);
$socials = (array) old('socials', $pro['socials'] ?? []);
?>
<div class="pro-head">
  <div><p class="mono muted small">Ma fiche</p><h1 class="h2">Votre vitrine <span class="serif c-coral">en ligne</span></h1></div>
  <div class="gauge gauge-sm" style="--p:<?= (int) App\Services\Pros::completeness($pro) ?>" title="Complétude"><span><?= (int) App\Services\Pros::completeness($pro) ?> %</span></div>
</div>
<form class="form mt-2" method="post" action="/espace-pro/fiche/" id="fiche-form">
  <?= csrf_field() ?>
  <div class="box">
    <h2>Présentation</h2>
    <div class="form-grid">
      <div class="field"><label for="f-name">Nom affiché <span class="req">*</span></label><input id="f-name" type="text" name="display_name" maxlength="80" required value="<?= e($v('display_name', App\Services\Pros::displayName($pro))) ?>"><?= field_error('display_name') ?></div>
      <div class="field"><label for="f-co">Raison sociale</label><input id="f-co" type="text" name="company" maxlength="100" value="<?= e($v('company')) ?>"></div>
      <div class="field"><label for="f-fn">Prénom</label><input id="f-fn" type="text" name="first_name" maxlength="60" value="<?= e($v('first_name')) ?>"><span class="hint">Non affiché publiquement.</span></div>
      <div class="field"><label for="f-ln">Nom</label><input id="f-ln" type="text" name="last_name" maxlength="60" value="<?= e($v('last_name')) ?>"></div>
    </div>
    <div class="field mt-2"><label for="f-tag">Accroche</label><input id="f-tag" type="text" name="tagline" maxlength="220" value="<?= e($v('tagline')) ?>" data-charcount="220" placeholder="Ex. : DJ mariage et soirées privées, sono & lumières professionnelles"></div>
    <div class="field mt-2">
      <label for="f-desc">Description de votre prestation</label>
      <textarea id="f-desc" name="description" data-editor="basic" rows="12"><?= e(old('description', $pro['description'] ?? '')) ?></textarea>
      <span class="hint">Déroulé, formules, matériel, expérience, zone d'intervention… 600 caractères minimum conseillés.</span>
      <?= field_error('description') ?>
      <?php if ($aiOn): ?>
        <button type="button" class="btn btn-sm btn-lime mt-1" data-ai-desc><?= icon('sparkles', 16) ?> Améliorer mon texte avec l'IA</button>
        <span class="hint">L'IA restructure votre texte sans rien inventer. Relisez avant d'enregistrer.</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="box">
    <h2>Métiers et mots-clés</h2>
    <fieldset class="field">
      <legend class="label">Métiers (4 maximum, le premier est votre métier principal) <span class="req">*</span></legend>
      <div class="choice-grid" data-max-check="4">
        <?php foreach (Categories::all() as $slug => $cat): ?>
          <label class="choice"><input type="checkbox" name="categories[]" value="<?= e($slug) ?>"<?= in_array($slug, $cats, true) ? ' checked' : '' ?>><span><?= e($cat['emoji'] . ' ' . $cat['name']) ?></span></label>
        <?php endforeach; ?>
      </div>
      <?= field_error('categories') ?>
    </fieldset>
    <div class="field mt-2"><label for="f-tags">Mots-clés</label><input id="f-tags" type="text" name="tags" maxlength="400" value="<?= e(is_array($t = old('tags', $pro['tags'] ?? [])) ? implode(', ', $t) : $t) ?>" placeholder="DJ mariage, karaoké, années 80, animation micro…"><span class="hint">Séparés par des virgules (10 maximum) : ils aident les clients à vous trouver.</span></div>
  </div>

  <div class="box">
    <h2>Localisation et zone d'intervention</h2>
    <div class="form-grid">
      <div class="field autocomplete"><label for="f-city">Ville <span class="req">*</span></label>
        <input id="f-city" type="text" name="city" autocomplete="off" data-commune-input value="<?= e($commune ? Geo::label($commune) : ($pro['city'] ?? '')) ?>">
        <input type="hidden" name="insee" data-commune-insee value="<?= e($pro['insee'] ?? '') ?>"><?= field_error('city') ?></div>
      <div class="field"><label for="f-cp">Code postal</label><input id="f-cp" type="text" name="postcode" maxlength="5" inputmode="numeric" value="<?= e($v('postcode')) ?>"></div>
      <div class="field"><label for="f-addr">Adresse</label><input id="f-addr" type="text" name="address" maxlength="160" value="<?= e($v('address')) ?>"><span class="hint">Non affichée publiquement.</span></div>
    </div>
    <fieldset class="field mt-2">
      <legend class="label">Départements où vous intervenez (<?= (int) $maxZones ?> maximum)</legend>
      <select name="zones[]" multiple size="8" class="input multi" data-multi aria-label="Départements">
        <?php foreach (Geo::departements() as $code => $d): ?>
          <option value="<?= e($code) ?>"<?= in_array((string) $code, array_map('strval', $zones), true) ? ' selected' : '' ?>><?= e($code . ' — ' . $d['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="hint">Maintenez Ctrl (ou ⌘) pour en choisir plusieurs.</span>
      <?= field_error('zones') ?>
    </fieldset>
    <label class="check mt-2"><input type="checkbox" name="all_france" value="1"<?= !empty($pro['all_france']) ? ' checked' : '' ?>> <span>Je me déplace dans toute la France</span></label>
  </div>

  <div class="box">
    <h2>Tarifs et contact</h2>
    <div class="form-grid">
      <div class="field"><label for="f-price">Prix de départ (€)</label><input id="f-price" type="number" name="price_from" min="0" max="100000" step="10" value="<?= e((string) ($pro['price_from'] ?? '')) ?>" placeholder="ex. 450"><span class="hint">Affiché « dès … € ». Laissez vide pour « sur devis ».</span></div>
      <div class="field"><label for="f-pnote">Précision sur le tarif</label><input id="f-pnote" type="text" name="price_note" maxlength="80" value="<?= e($v('price_note')) ?>" placeholder="ex. formule 5 h, sono incluse"></div>
      <div class="field"><label for="f-phone">Téléphone</label><input id="f-phone" type="tel" name="phone" maxlength="30" value="<?= e($v('phone')) ?>"><span class="hint">Affiché aux clients après un clic (protégé des robots).</span><?= field_error('phone') ?></div>
      <div class="field"><label for="f-web">Site web</label><input id="f-web" type="url" name="website" maxlength="255" value="<?= e($v('website')) ?>" placeholder="https://"><?= field_error('website') ?></div>
    </div>
    <div class="form-grid mt-2">
      <?php foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn'] as $net => $label): ?>
        <div class="field"><label for="f-<?= $net ?>"><?= icon($net, 14) ?> <?= e($label) ?></label><input id="f-<?= $net ?>" type="url" name="socials[<?= $net ?>]" maxlength="255" value="<?= e($socials[$net] ?? '') ?>" placeholder="https://"></div>
      <?php endforeach; ?>
    </div>
    <?= field_error('socials') ?>
    <label class="check mt-2"><input type="checkbox" name="accept_requests" value="1"<?= ($pro['accept_requests'] ?? true) !== false ? ' checked' : '' ?>> <span>Je souhaite recevoir les demandes de devis des clients de mon secteur</span></label>
  </div>

  <div class="box">
    <h2>Vidéos</h2>
    <div class="field"><label for="f-videos">Liens YouTube ou Vimeo (un par ligne, 4 maximum)</label>
      <textarea id="f-videos" name="videos" rows="3" placeholder="https://www.youtube.com/watch?v=…"><?= e(implode("\n", (array) ($pro['videos'] ?? []))) ?></textarea><?= field_error('videos') ?></div>
  </div>

  <details class="box">
    <summary><h2 style="display:inline">Informations administratives</h2> <span class="muted small">(facultatif, non publiées)</span></summary>
    <div class="form-grid mt-2">
      <div class="field"><label for="f-siret">SIRET</label><input id="f-siret" type="text" name="siret" maxlength="20" inputmode="numeric" value="<?= e($v('siret')) ?>"></div>
      <div class="field"><label for="f-guso">N° GUSO / licence</label><input id="f-guso" type="text" name="guso" maxlength="30" value="<?= e($v('guso')) ?>"></div>
      <div class="field"><label for="f-lang">Langues parlées</label><input id="f-lang" type="text" name="languages" maxlength="80" value="<?= e($v('languages')) ?>" placeholder="français, anglais"></div>
      <div class="field"><label for="f-exp">En activité depuis</label><input id="f-exp" type="number" name="experience_since" min="1950" max="<?= date('Y') ?>" value="<?= e((string) ($pro['experience_since'] ?? '')) ?>" placeholder="ex. 2008"></div>
    </div>
  </details>

  <div class="sticky-save"><button class="btn btn-coral" type="submit"><?= icon('check', 18) ?> Enregistrer ma fiche</button></div>
</form>
