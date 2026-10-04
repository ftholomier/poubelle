<?php
/** Devenir bénévole. Variables : $p, $poles, $flash, $old */
use App\Vitrine\Content;
use App\Vitrine\Forms;
use App\Vitrine\Host;
use App\Vitrine\Pages;

$o = fn (string $k) => is_array($old[$k] ?? null) ? $old[$k] : (string) ($old[$k] ?? '');
$sel = (array) ($old['poles'] ?? []);
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => $p['title'], 'lead' => $p['lead'], 'eyebrow' => 'Nous soutenir', 'image' => Content::hasImage($p['image'] ?? null) ? $p['image'] : null, 'crumbs' => [['Nous soutenir', Host::url('/nous-soutenir/')], ['Bénévolat', Host::url('/nous-soutenir/benevolat/')]]]) ?>

<section class="section--tight">
  <div class="wrap">
    <div class="prose vlead-prose" style="max-width:70ch"><?= Pages::rich($p['intro'] ?? '') ?></div>
    <div class="vpoles mt-40">
      <?php foreach ($poles as $po): if (($po['key'] ?? '') === 'bureau') continue; ?>
        <div class="vpole" data-reveal><span class="vpole__i" aria-hidden="true"><?= e($po['icon'] ?? '★') ?></span><h3><?= e($po['title']) ?></h3><p><?= e($po['text']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section bg-sand bt" id="formulaire">
  <div class="wrap vform-wrap">
    <form class="vform" method="post" action="<?= e(Host::url('/nous-soutenir/benevolat/')) ?>" data-protect>
      <?= csrf_field() ?>
      <input type="hidden" name="_ts" value="<?= e(form_ts()) ?>">
      <div class="hp" aria-hidden="true"><label for="ben-w">Ne pas remplir</label><input type="text" id="ben-w" name="website" tabindex="-1" autocomplete="off"></div>
      <h2 class="h-2"><?= e($p['form_title'] ?? '') ?></h2>
      <p style="margin:0"><?= e($p['form_text'] ?? '') ?></p>
      <?= \App\Core\View::partial('vitrine/partials/flash', ['flash' => $flash]) ?>
      <div class="vgrid2">
        <div class="field"><label for="ben-first">Prénom *</label><input type="text" id="ben-first" name="first" required maxlength="60" autocomplete="given-name" value="<?= e($o('first')) ?>"></div>
        <div class="field"><label for="ben-last">Nom</label><input type="text" id="ben-last" name="last" maxlength="80" autocomplete="family-name" value="<?= e($o('last')) ?>"></div>
        <div class="field"><label for="ben-email">E-mail *</label><input type="email" id="ben-email" name="email" required maxlength="160" autocomplete="email" value="<?= e($o('email')) ?>"></div>
        <div class="field"><label for="ben-phone">Téléphone</label><input type="tel" id="ben-phone" name="phone" maxlength="30" autocomplete="tel" value="<?= e($o('phone')) ?>"></div>
        <div class="field"><label for="ben-city">Ville</label><input type="text" id="ben-city" name="city" maxlength="80" autocomplete="address-level2" value="<?= e($o('city')) ?>"></div>
        <div class="field"><label for="ben-av">Disponibilité</label><select id="ben-av" name="availability"><option value="">—</option><?php foreach (Forms::AVAILABILITY as $k => $l): ?><option value="<?= e($k) ?>"<?= $o('availability') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
      <fieldset class="vfieldset">
        <legend class="label">Ce qui vous plairait</legend>
        <div class="vchecks">
          <?php foreach ($poles as $po): if (($po['key'] ?? '') === 'bureau') continue; ?>
            <label class="checkbox"><input type="checkbox" name="poles[]" value="<?= e($po['key']) ?>"<?= in_array($po['key'], $sel, true) ? ' checked' : '' ?>> <span><?= e($po['title']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="field"><label for="ben-skills">Vos compétences ou passions</label><input type="text" id="ben-skills" name="skills" maxlength="500" placeholder="Scanner, montage vidéo, rédaction, collection de programmes…" value="<?= e($o('skills')) ?>"></div>
      <div class="field"><label for="ben-msg">Un mot pour nous ?</label><textarea id="ben-msg" name="message" maxlength="4000" rows="5"><?= e($o('message')) ?></textarea></div>
      <label class="checkbox"><input type="checkbox" name="consent" value="1" required> <span>J’accepte que mes coordonnées soient utilisées pour être recontacté par l’association (<a href="<?= e(Host::url('/confidentialite/')) ?>">confidentialité</a>). *</span></label>
      <button type="submit" class="btn btn--navy btn--block">Envoyer ma proposition</button>
    </form>
    <aside class="vform-side">
      <div class="vbox vbox--y"><h2 class="h-3">Depuis chez vous</h2><p class="mt-20" style="font-size:17px">Beaucoup de missions se font à distance : vérifier une fiche, légender des photos, scanner vos archives, monter une vidéo.</p></div>
      <div class="vbox"><h2 class="h-3">Vous avez des archives ?</h2><p class="mt-20" style="font-size:17px">Pas besoin d’être bénévole pour les partager : un scan suffit.</p><a class="btn btn--ghost btn--block" href="<?= e(Host::museum('/contribuer/')) ?>" target="_blank" rel="noopener">Confier une archive ↗</a></div>
    </aside>
  </div>
</section>
