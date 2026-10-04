<?php
/** Adhérer. Variables : $p, $tariffs, $methods, $helloasso, $address, $flash, $old, $test */
use App\Vitrine\Host;
use App\Vitrine\Pages;
use App\Vitrine\Site;

$o = fn (string $k, string $d = '') => (string) ($old[$k] ?? $d);
$sel = $o('tariff', $tariffs[0]['key'] ?? '');
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => $p['title'], 'lead' => $p['lead'], 'eyebrow' => 'Nous soutenir', 'crumbs' => [['Nous soutenir', Host::url('/nous-soutenir/')], ['Adhérer', Host::url('/nous-soutenir/adherer/')]]]) ?>

<section class="section--tight">
  <div class="wrap">
    <h2 class="h-2"><?= e($p['benefits_title'] ?? '') ?></h2>
    <div class="vbenefits mt-40">
      <?php foreach ($p['benefits'] ?? [] as $b): ?><div class="vbenefit" data-reveal><h3><?= e($b['title']) ?></h3><p><?= e($b['text']) ?></p></div><?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section bg-sand bt" id="formulaire">
  <div class="wrap vform-wrap">
    <form class="vform" method="post" action="<?= e(Host::url('/nous-soutenir/adherer/')) ?>" data-protect data-adh>
      <?= csrf_field() ?>
      <input type="hidden" name="_ts" value="<?= e(form_ts()) ?>">
      <div class="hp" aria-hidden="true"><label for="adh-w">Ne pas remplir</label><input type="text" id="adh-w" name="website" tabindex="-1" autocomplete="off"></div>
      <h2 class="h-2">Votre adhésion</h2>
      <?= \App\Core\View::partial('vitrine/partials/flash', ['flash' => $flash]) ?>
      <?php if ($tariffs): ?>
      <fieldset class="vtariffs">
        <legend class="label">1. Choisissez votre formule <span class="muted">· <?= e($p['validity'] ?? '') ?></span></legend>
        <?php foreach ($tariffs as $t): ?>
          <label class="vtariff">
            <input type="radio" name="tariff" value="<?= e($t['key']) ?>"<?= $t['key'] === $sel ? ' checked' : '' ?> data-amount="<?= (int) $t['amount'] ?>"<?= !empty($t['free']) ? ' data-free' : '' ?> required>
            <span class="vtariff__box">
              <b class="vtariff__a"><?= !empty($t['free']) ? 'dès ' : '' ?><?= (int) $t['amount'] ?> €</b>
              <span class="vtariff__l"><?= e($t['label']) ?></span>
              <span class="vtariff__t"><?= e($t['text'] ?? '') ?></span>
            </span>
          </label>
        <?php endforeach; ?>
        <?php foreach ($tariffs as $t): if (empty($t['free'])) continue; ?>
          <div class="field vtariff-free" data-free-for="<?= e($t['key']) ?>">
            <label for="adh-amount">Montant de votre soutien (€)</label>
            <input type="number" id="adh-amount" name="amount" min="<?= (int) $t['amount'] ?>" max="5000" step="1" value="<?= e($o('amount', (string) $t['amount'])) ?>" inputmode="numeric">
          </div>
        <?php break; endforeach; ?>
      </fieldset>
      <?php endif; ?>
      <fieldset class="vfieldset">
        <legend class="label">2. Vos coordonnées</legend>
        <div class="vgrid2">
          <div class="field"><label for="adh-first">Prénom *</label><input type="text" id="adh-first" name="first" required maxlength="60" autocomplete="given-name" value="<?= e($o('first')) ?>"></div>
          <div class="field"><label for="adh-last">Nom *</label><input type="text" id="adh-last" name="last" required maxlength="80" autocomplete="family-name" value="<?= e($o('last')) ?>"></div>
          <div class="field"><label for="adh-email">E-mail *</label><input type="email" id="adh-email" name="email" required maxlength="160" autocomplete="email" value="<?= e($o('email')) ?>"></div>
          <div class="field"><label for="adh-phone">Téléphone</label><input type="tel" id="adh-phone" name="phone" maxlength="30" autocomplete="tel" value="<?= e($o('phone')) ?>"></div>
          <div class="field vgrid2__full"><label for="adh-address">Adresse</label><input type="text" id="adh-address" name="address" maxlength="160" autocomplete="street-address" value="<?= e($o('address')) ?>"></div>
          <div class="field"><label for="adh-zip">Code postal</label><input type="text" id="adh-zip" name="zip" maxlength="12" autocomplete="postal-code" inputmode="numeric" value="<?= e($o('zip')) ?>"></div>
          <div class="field"><label for="adh-city">Ville</label><input type="text" id="adh-city" name="city" maxlength="80" autocomplete="address-level2" value="<?= e($o('city')) ?>"></div>
          <div class="field vgrid2__full" data-family><label for="adh-family">Membres de la famille (adhésion famille)</label><input type="text" id="adh-family" name="family" maxlength="300" placeholder="Prénoms et noms" value="<?= e($o('family')) ?>"></div>
          <div class="field vgrid2__full"><label for="adh-source">Comment avez-vous connu l’association ? <span class="muted">(facultatif)</span></label><input type="text" id="adh-source" name="source" maxlength="200" value="<?= e($o('source')) ?>"></div>
        </div>
      </fieldset>
      <fieldset class="vfieldset">
        <legend class="label">3. Règlement</legend>
        <div class="vpay">
          <?php foreach ($methods as $k => $l): ?>
            <label class="vpay__opt"><input type="radio" name="method" value="<?= e($k) ?>"<?= $o('method', (string) array_key_first($methods)) === $k ? ' checked' : '' ?>><span><b><?= e($l) ?></b><small>Paiement sécurisé en ligne<?= $k === 'stripe' ? ' (Stripe)' : '' ?></small></span></label>
          <?php endforeach; ?>
          <label class="vpay__opt"><input type="radio" name="method" value="cheque"<?= !$methods || $o('method') === 'cheque' ? ' checked' : '' ?>><span><b>Chèque ou en main propre</b><small>Vous recevez le récapitulatif à joindre à votre règlement</small></span></label>
        </div>
        <?php if ($methods && $test): ?><p class="alert" style="margin-top:14px">Paiements en ligne en <b>mode test</b> : aucun prélèvement réel.</p><?php endif; ?>
      </fieldset>
      <label class="checkbox"><input type="checkbox" name="newsletter" value="1"<?= $o('newsletter') !== '' ? ' checked' : '' ?>> <span>Je souhaite aussi recevoir la newsletter « Ce jour-là » (désinscription en un clic).</span></label>
      <label class="checkbox"><input type="checkbox" name="consent" value="1" required> <span>J’accepte que mes coordonnées soient utilisées pour la gestion de mon adhésion et la vie de l’association (<a href="<?= e(Host::url('/confidentialite/')) ?>">confidentialité</a>). *</span></label>
      <button type="submit" class="btn btn--navy btn--block" data-adh-submit>Valider mon adhésion</button>
      <p class="muted" style="font-size:15px;margin:0">* Champs obligatoires.</p>
    </form>
    <aside class="vform-side">
      <?php if ($helloasso !== ''): ?>
        <div class="vbox"><h2 class="h-3">Avec HelloAsso</h2><p class="mt-20" style="font-size:17px">Vous préférez passer par la plateforme HelloAsso ?</p><a class="btn btn--ghost btn--block" href="<?= e($helloasso) ?>" target="_blank" rel="noopener">Adhérer avec HelloAsso ↗</a></div>
      <?php endif; ?>
      <div class="vbox">
        <h2 class="h-3"><?= e($p['paper_title'] ?? '') ?></h2>
        <div class="prose mt-20" style="font-size:17px"><?= Pages::rich($p['paper_text'] ?? '') ?></div>
        <?php if ($address): ?><div class="vaddr mt-20"><?= $address ?></div><?php endif; ?>
        <a class="btn btn--ghost btn--block mt-20" href="<?= e(Host::url('/nous-soutenir/adherer/bulletin/')) ?>" target="_blank" rel="noopener">Imprimer le bulletin</a>
      </div>
      <div class="vbox vbox--y"><h2 class="h-3">Une question ?</h2><p class="mt-20" style="font-size:17px">Sur l’adhésion, les cotisations ou la vie de l’association.</p><a class="link-under" href="<?= e(Host::url('/contact/?objet=adhesion')) ?>">Nous écrire</a></div>
    </aside>
  </div>
</section>
