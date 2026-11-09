<?php
$pg = Content::page('contact');
$site = Content::site();
$ui = Content::ui();
$choices = Content::devisChoices();
[$mu, $md] = Content::emailParts();
$sent = isset($_GET['envoye']);
View::render('partials/band', ['eyebrow' => $pg['eyebrow'] ?? '', 'h1' => $pg['title'], 'lead' => $pg['lead'] ?? '', 'small' => true]);
?>
<div class="wrap" style="padding-top:36px">
  <div class="grid-ag">
    <?php foreach (($site['agencies'] ?? []) as $i => $a): ?>
      <div class="ag" data-reveal="up" data-reveal-delay="<?= $i * 110 ?>">
        <div class="ag__e">Agence</div>
        <div class="ag__v"><?= e($a['city']) ?></div>
        <div class="ybar" style="width:40px"></div>
        <div class="ag__a"><?= e($a['street']) ?><br><?= e(trim($a['zip'] . ' ' . $a['town'] . ' ' . ($a['country'] ?? ''))) ?></div>
        <?php $aTel = trim((string) ($a['phone'] ?? '')) ?: $site['phone']; ?>
        <a class="ag__t" href="tel:<?= e(tel($aTel)) ?>"><?= e($aTel) ?></a>
        <div class="ag__h"><?= e(trim((string) ($a['hours'] ?? '')) ?: $site['hours']) ?></div>
      </div>
    <?php endforeach; ?>
    <div class="ag ag--dark" data-reveal="up" data-reveal-delay="220">
      <div>
        <div class="ag__w"><?= e($ui['cta_bar']['contact'] ?? 'Contactez-nous') ?></div>
        <div class="ag__l">
          Courriel : <a href="#" data-mail="<?= e($mu) ?>" data-domain="<?= e($md) ?>"><?= e($mu) ?>[@]<?= e(str_replace('.', '[.]', $md)) ?></a><br>
          Tél : <?= e($site['phone']) ?><br>
          Fax : <?= e($site['fax']) ?><br>
          Service Technique : <a href="tel:<?= e(tel($site['sav_phone'])) ?>"><?= e($site['sav_phone']) ?></a>
        </div>
      </div>
      <?= View::btn($ui['cta_bar']['devis'] ?? 'Demandez un devis', Content::path('devis'), 'btn-y') ?>
    </div>
  </div>

  <div class="cform">
    <div class="dv__card" data-reveal="up">
      <form id="rv-contact" action="/api/contact" method="post" novalidate<?= $sent ? ' hidden' : '' ?>>
        <input type="hidden" name="token" value="<?= e(Http::formToken()) ?>">
        <div class="hp" aria-hidden="true"><label>Site web <input type="text" name="site" tabindex="-1" autocomplete="off"></label></div>
        <div class="dv__h"><?= e($pg['form_title'] ?? 'Formulaire de contact') ?></div>
        <div class="fields">
          <div class="fields--2">
            <label class="field"><span>Nom / Prénom *</span><input name="nom" maxlength="120" autocomplete="name" required></label>
            <label class="field"><span>Téléphone</span><input name="tel" type="tel" maxlength="30" autocomplete="tel"></label>
          </div>
          <label class="field"><span>Adresse e-mail *</span><input name="email" type="email" maxlength="160" autocomplete="email" required></label>
          <div class="field"><span>Objet</span>
            <div class="radios">
              <label><input type="radio" name="objet" value="Message" checked> Message</label>
              <label><input type="radio" name="objet" value="Devis"> Devis</label>
            </div>
          </div>
          <div class="field" data-when="Devis" hidden><span>Devis</span>
            <div class="radios">
              <?php foreach ($choices as $c): ?><label><input type="checkbox" name="types[]" value="<?= e($c) ?>"> <?= e($c) ?></label><?php endforeach; ?>
            </div>
          </div>
          <label class="field"><span data-label-msg="Message" data-label-devis="Observations">Message</span><textarea name="msg" rows="5" maxlength="2000"></textarea></label>
        </div>
        <div class="dv__nav"><button class="dv__next is-valid" type="submit">Envoyer</button></div>
        <div class="dv__err" hidden></div>
        <div class="dv__legal"><?= e($ui['devis_form']['legal'] ?? '') ?></div>
      </form>
      <div class="dv__ok"<?= $sent ? '' : ' hidden' ?>>
        <div class="dv__okc">✓</div>
        <div class="dv__okt">Message envoyé !</div>
        <p class="dv__okp"><?= e(Content::page('devis')['confirm_text'] ?? '') ?></p>
      </div>
    </div>
    <?php if (!empty($site['map_embed'])): ?>
      <div class="map" data-reveal="up" data-reveal-delay="120" data-map="<?= e($site['map_embed']) ?>">
        <button type="button" class="btn btn-y btn-sm" style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%)"><span class="fx"></span><span>Afficher la carte</span></button>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($site['zones'])): ?>
  <div class="zones" data-reveal="up">
    <div class="zones__t">Zone d'intervention</div>
    <div class="zones__l"><?php foreach ($site['zones'] as $z): ?><span><?= e($z) ?></span><?php endforeach; ?></div>
  </div>
  <?php endif; ?>
</div>
