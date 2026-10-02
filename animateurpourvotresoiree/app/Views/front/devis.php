<?php
use App\Core\View;
use App\Services\AntiSpam;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Leads;

/** @var array $targets @var array $prefill */
$c = $prefill['commune'];
$types = ['mariage' => '💍 Mariage', 'anniversaire' => '🎂 Anniversaire', 'entreprise' => '💼 Entreprise', 'soiree' => '🪩 Soirée privée', 'bapteme' => '🕊️ Baptême', 'association' => '🎪 Fête publique', 'autre' => '✨ Autre'];
?>
<section class="page-head" style="max-width:980px">
  <?= View::partial('front/partials/crumbs', ['crumbs' => [['Accueil', '/'], ['Demande de devis', '/devis/']]]) ?>
  <div class="badge badge-tilt mt-2">● Gratuit · sans engagement · réponse en 24 h</div>
  <h1>Vos devis <span class="serif">en une minute</span></h1>
  <p class="lead">Décrivez votre fête : nous transmettons votre demande aux professionnels de votre secteur, qui vous contactent directement. Aucune commission, aucun intermédiaire.</p>
</section>

<section class="section section-tight" style="max-width:980px" id="formulaire">
  <form class="form form-card" method="post" action="/devis/" data-ajax data-protect="devis" novalidate>
    <?= AntiSpam::fields('devis') ?>
    <input type="hidden" name="source" value="<?= e($prefill['source'] === 'assistant' ? 'assistant' : ($targets ? 'favoris' : 'form')) ?>">
    <?php if ($targets): ?>
      <div class="alert alert-info"><div><strong>Demande groupée</strong> — elle sera envoyée à : <?= e(implode(', ', array_map(static fn ($p) => $p['name'], $targets))) ?>.
        <?php foreach ($targets as $id => $p): ?><input type="hidden" name="pros[]" value="<?= (int) $id ?>"><?php endforeach; ?></div></div>
    <?php endif; ?>

    <h2 class="h3">1. Votre événement</h2>
    <fieldset class="field">
      <legend class="label">Type d'événement</legend>
      <div class="choice-grid">
        <?php foreach ($types as $k => $label): ?>
          <label class="choice"><input type="radio" name="event_type" value="<?= e($k) ?>"<?= ($prefill['type'] ?: 'mariage') === $k ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <div class="form-grid">
      <div class="field autocomplete">
        <label for="dv-city">Ville de l'événement <span class="req">*</span></label>
        <input id="dv-city" type="text" name="city" value="<?= e($c ? Geo::label($c) : old('city')) ?>" placeholder="Lyon, 69003…" autocomplete="off" required data-commune-input>
        <input type="hidden" name="insee" value="<?= e($c['insee'] ?? old('insee')) ?>" data-commune-insee>
        <?= field_error('city') ?>
      </div>
      <div class="field"><label for="dv-place">Lieu / salle</label><input id="dv-place" type="text" name="place" maxlength="120" value="<?= e(old('place')) ?>" placeholder="Salle des fêtes, domaine…"></div>
      <div class="field"><label for="dv-date">Date</label><input id="dv-date" type="date" name="date" value="<?= e($prefill['date'] ?: old('date')) ?>" min="<?= date('Y-m-d') ?>"><label class="check small mt-1"><input type="checkbox" name="date_flexible" value="1"> Date flexible</label></div>
      <div class="field"><label for="dv-guests">Nombre d'invités</label><input id="dv-guests" type="text" name="guests" maxlength="30" value="<?= e($prefill['guests'] ?: old('guests')) ?>" placeholder="ex. 120"></div>
      <div class="field"><label for="dv-budget">Budget indicatif</label>
        <select id="dv-budget" name="budget"><option value="">À définir</option><option>Moins de 500 €</option><option>500 à 1 000 €</option><option>1 000 à 2 000 €</option><option>2 000 à 5 000 €</option><option>Plus de 5 000 €</option></select>
      </div>
    </div>

    <h2 class="h3 mt-2">2. Ce que vous cherchez</h2>
    <fieldset class="field">
      <legend class="label">Prestations (plusieurs choix possibles)</legend>
      <div class="choice-grid">
        <?php foreach (Categories::all() as $slug => $cat): ?>
          <label class="choice"><input type="checkbox" name="categories[]" value="<?= e($slug) ?>"<?= $prefill['cat'] === $slug ? ' checked' : '' ?>><span><?= e($cat['emoji'] . ' ' . $cat['name']) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <div class="field">
      <label for="dv-msg">Votre demande <span class="req">*</span></label>
      <textarea id="dv-msg" name="message" maxlength="5000" required data-charcount="5000" placeholder="Ex. : mariage de 120 personnes, vin d'honneur en extérieur puis soirée dansante années 80 à aujourd'hui, de 19 h à 3 h…"><?= e($prefill['details'] ?: old('message')) ?></textarea>
      <span class="hint">Plus vous êtes précis (horaires, ambiance, matériel, contraintes du lieu), plus les devis seront justes.</span>
      <?= field_error('message') ?>
    </div>

    <h2 class="h3 mt-2">3. Vos coordonnées</h2>
    <div class="choice-grid">
      <?php foreach (['particulier' => 'Particulier', 'societe' => 'Entreprise / CSE', 'association' => 'Association / mairie'] as $k => $l): ?>
        <label class="choice"><input type="radio" name="client_type" value="<?= $k ?>"<?= $k === 'particulier' ? ' checked' : '' ?>><span><?= e($l) ?></span></label>
      <?php endforeach; ?>
    </div>
    <div class="form-grid">
      <div class="field"><label for="dv-fn">Prénom <span class="req">*</span></label><input id="dv-fn" type="text" name="first_name" maxlength="60" required autocomplete="given-name" value="<?= e(old('first_name')) ?>"><?= field_error('first_name') ?></div>
      <div class="field"><label for="dv-ln">Nom</label><input id="dv-ln" type="text" name="last_name" maxlength="60" autocomplete="family-name" value="<?= e(old('last_name')) ?>"></div>
      <div class="field"><label for="dv-co">Société / organisme</label><input id="dv-co" type="text" name="company" maxlength="100" autocomplete="organization" value="<?= e(old('company')) ?>"></div>
      <div class="field"><label for="dv-em">Email <span class="req">*</span></label><input id="dv-em" type="email" name="email" maxlength="120" required autocomplete="email" value="<?= e(old('email')) ?>"><?= field_error('email') ?></div>
      <div class="field"><label for="dv-ph">Téléphone</label><input id="dv-ph" type="tel" name="phone" maxlength="30" autocomplete="tel" value="<?= e(old('phone')) ?>"><span class="hint">Conseillé : les pros répondent plus vite.</span></div>
    </div>
    <label class="check field"><input type="checkbox" name="consent" value="1" required> <span class="small">J'accepte que ma demande et mes coordonnées soient transmises aux professionnels sélectionnés pour qu'ils me répondent. <a href="/confidentialite/">En savoir plus</a></span></label>
    <?= field_error('consent') ?>
    <div class="row-wrap"><button type="submit" class="btn btn-coral">Envoyer ma demande gratuite →</button><span class="pow-status"></span></div>
    <div data-form-result></div>
  </form>
</section>
