<?php
use App\Core\View;
use App\Services\AntiSpam;
use App\Services\Categories;
/** @var bool $open */
?>
<section class="page-head" style="max-width:1080px">
  <?= View::partial('front/partials/crumbs', ['crumbs' => [['Accueil', '/'], ['Espace pros', '/professionnels/'], ['Inscription', '/inscription-pro/']]]) ?>
  <div class="badge badge-tilt mt-2">● 100 % gratuit · sans commission</div>
  <h1>Votre agenda mérite d'être <span class="serif">plein</span></h1>
  <p class="lead">DJ, magicien, animateur, groupe, photobooth… Créez votre fiche en 5 minutes et recevez des demandes de clients près de chez vous.</p>
</section>
<section class="section section-tight register-grid" style="max-width:1080px" id="formulaire">
  <?php if (!$open): ?>
    <div class="alert alert-warning">Les inscriptions sont momentanément fermées. Revenez bientôt ou <a href="/contact/">contactez-nous</a>.</div>
  <?php else: ?>
  <form class="form form-card" method="post" action="/inscription-pro/" data-ajax data-protect="register" novalidate>
    <?= AntiSpam::fields('register') ?>
    <div class="form-steps"><span class="on">1. Votre activité</span><span>2. Vos coordonnées</span><span>3. Votre accès</span></div>
    <div class="form-grid">
      <div class="field"><label for="rg-name">Nom de scène / société <span class="req">*</span></label><input id="rg-name" type="text" name="display_name" maxlength="80" required value="<?= e(old('display_name')) ?>" placeholder="DJ Max Événements"><?= field_error('display_name') ?></div>
      <div class="field autocomplete"><label for="rg-city">Ville <span class="req">*</span></label>
        <input id="rg-city" type="text" name="city" autocomplete="off" required data-commune-input placeholder="Votre ville" value="<?= e(old('city')) ?>">
        <input type="hidden" name="insee" data-commune-insee value="<?= e(old('insee')) ?>"><?= field_error('city') ?></div>
    </div>
    <fieldset class="field">
      <legend class="label">Vos métiers (3 maximum) <span class="req">*</span></legend>
      <div class="choice-grid" data-max-check="3">
        <?php foreach (Categories::all() as $slug => $cat): ?>
          <label class="choice"><input type="checkbox" name="categories[]" value="<?= e($slug) ?>"><span><?= e($cat['emoji'] . ' ' . $cat['name']) ?></span></label>
        <?php endforeach; ?>
      </div>
      <?= field_error('categories') ?>
    </fieldset>
    <div class="field"><label for="rg-tag">Votre accroche</label><input id="rg-tag" type="text" name="tagline" maxlength="220" value="<?= e(old('tagline')) ?>" placeholder="DJ mariage & soirées depuis 15 ans, sono et lumières pro"><span class="hint">Elle apparaît sous votre nom dans les résultats.</span></div>
    <div class="form-grid">
      <div class="field"><label for="rg-fn">Prénom <span class="req">*</span></label><input id="rg-fn" type="text" name="first_name" maxlength="60" required autocomplete="given-name" value="<?= e(old('first_name')) ?>"><?= field_error('first_name') ?></div>
      <div class="field"><label for="rg-ln">Nom</label><input id="rg-ln" type="text" name="last_name" maxlength="60" autocomplete="family-name" value="<?= e(old('last_name')) ?>"></div>
      <div class="field"><label for="rg-ph">Téléphone <span class="req">*</span></label><input id="rg-ph" type="tel" name="phone" maxlength="30" required autocomplete="tel" value="<?= e(old('phone')) ?>"><?= field_error('phone') ?></div>
      <div class="field"><label for="rg-web">Site web</label><input id="rg-web" type="url" name="website" maxlength="200" placeholder="https://" value="<?= e(old('website')) ?>"></div>
      <div class="field"><label for="rg-siret">SIRET <?= App\Services\Settings::get('registration.require_siren') ? '<span class="req">*</span>' : '<span class="muted">(facultatif)</span>' ?></label><input id="rg-siret" type="text" name="siret" maxlength="20" inputmode="numeric" value="<?= e(old('siret')) ?>"><?= field_error('siret') ?></div>
    </div>
    <div class="form-grid">
      <div class="field"><label for="rg-em">Email (identifiant) <span class="req">*</span></label><input id="rg-em" type="email" name="email" maxlength="120" required autocomplete="email" value="<?= e(old('email')) ?>"><?= field_error('email') ?></div>
      <div class="field"><label for="rg-pw">Mot de passe <span class="req">*</span></label><input id="rg-pw" type="password" name="password" required autocomplete="new-password" minlength="<?= max(8, (int) env('PASSWORD_MIN_LENGTH', 10)) ?>" data-strength><span class="hint"><?= max(8, (int) env('PASSWORD_MIN_LENGTH', 10)) ?> caractères minimum, lettres et chiffres.</span><?= field_error('password') ?></div>
    </div>
    <label class="check field"><input type="checkbox" name="cgu" value="1" required> <span class="small">J'accepte les <a href="/cgu/" target="_blank">conditions d'utilisation</a> et la <a href="/charte-qualite/" target="_blank">charte qualité</a>, et je certifie être un professionnel de l'animation ou de l'événementiel.</span></label>
    <label class="check"><input type="checkbox" name="newsletter" value="1" checked> <span class="small">Je souhaite recevoir les conseils et nouveautés du site (1 à 2 emails par mois maximum).</span></label>
    <div data-form-result></div>
    <button class="btn btn-coral btn-block" type="submit">Créer ma fiche gratuite →</button>
    <p class="pow-status"><?= icon('shield', 14) ?> Formulaire protégé contre les robots, sans captcha.</p>
  </form>
  <?php endif; ?>
  <aside class="stack">
    <div class="box box-ink">
      <h2 class="h3" style="color:var(--cream)">Pourquoi nous rejoindre ?</h2>
      <ul class="list-check light">
        <li>Inscription, fiche et demandes <strong>gratuites</strong></li>
        <li>Aucune commission sur vos prestations</li>
        <li>Des clients de votre secteur, par email et notification</li>
        <li>Votre fiche optimisée pour Google</li>
        <li>Avis clients vérifiés, statistiques détaillées</li>
      </ul>
    </div>
    <div class="box">
      <h2 class="h3">Déjà inscrit ?</h2>
      <p class="small">Les membres de l'ancien site gardent leur identifiant et leur mot de passe.</p>
      <a class="btn btn-ink btn-sm mt-1" href="/connexion/">Me connecter</a>
    </div>
  </aside>
</section>
