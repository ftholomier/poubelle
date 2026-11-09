<?php
$pg = Content::page('devis');
$site = Content::site();
$ui = Content::ui();
$f = $ui['devis_form'] ?? [];
$choices = Content::devisChoices();
$pre = (string) ($_GET['produit'] ?? '');
$preTel = mb_substr(preg_replace('/[^0-9+ .]/', '', (string) ($_GET['tel'] ?? '')) ?? '', 0, 20);
$step = (int) ($_GET['etape'] ?? 1);
$step = $step >= 1 && $step <= 3 ? $step : 1;
$sent = isset($_GET['envoye']);
View::render('partials/band', ['eyebrow' => $pg['eyebrow'] ?? '', 'h1' => $pg['h1'] ?? $pg['title'], 'lead' => $pg['lead'] ?? '', 'small' => true]);
?>
<div class="wrap">
  <div class="dv">
    <div class="dv__card" data-reveal="up">
      <form id="rv-devis" action="/api/devis" method="post" data-step="<?= $step ?>" novalidate<?= $sent ? ' hidden' : '' ?>>
        <input type="hidden" name="token" value="<?= e(Http::formToken()) ?>">
        <div class="hp" aria-hidden="true"><label>Site web <input type="text" name="site" tabindex="-1" autocomplete="off"></label></div>
        <div class="dv__steps">
          <?php foreach (($f['steps'] ?? ['Projet', 'Détails', 'Coordonnées']) as $i => $l): ?>
            <div class="dv__step<?= $i + 1 <= $step ? ' is-on' : '' ?>" data-bar="<?= $i + 1 ?>"><i></i><span><?= ($i + 1) . '. ' . e($l) ?></span></div>
          <?php endforeach; ?>
        </div>

        <fieldset data-pane="1" style="border:0;padding:0;margin:0"<?= $step !== 1 ? ' hidden' : '' ?>>
          <legend class="dv__h" style="padding:0"><?= e($f['step1_title'] ?? '') ?></legend>
          <div class="dv__sub"><?= e($f['step1_text'] ?? '') ?></div>
          <div class="dv__types">
            <?php foreach ($choices as $c): $on = $pre !== '' && mb_strtolower($pre) === mb_strtolower($c); ?>
              <label class="dv__type<?= $on ? ' is-on' : '' ?>"><input class="sr-only" type="checkbox" name="types[]" value="<?= e($c) ?>"<?= $on ? ' checked' : '' ?>><i></i><span><?= e($c) ?></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset data-pane="2" style="border:0;padding:0;margin:0"<?= $step !== 2 ? ' hidden' : '' ?>>
          <legend class="dv__h" style="padding:0"><?= e($f['step2_title'] ?? '') ?></legend>
          <div class="fields">
            <label class="field"><span>Nombre d'éléments</span>
              <select name="qty"><option value="">Sélectionner…</option><?php foreach (($f['qty'] ?? []) as $o): ?><option><?= e($o) ?></option><?php endforeach; ?></select>
            </label>
            <label class="field"><span>Échéance du projet</span>
              <select name="delai"><option value="">Sélectionner…</option><?php foreach (($f['delai'] ?? []) as $o): ?><option><?= e($o) ?></option><?php endforeach; ?></select>
            </label>
            <label class="field"><span>Code postal du chantier</span>
              <input name="cp" inputmode="numeric" maxlength="5" placeholder="25400" autocomplete="postal-code">
            </label>
          </div>
        </fieldset>

        <fieldset data-pane="3" style="border:0;padding:0;margin:0"<?= $step !== 3 ? ' hidden' : '' ?>>
          <legend class="dv__h" style="padding:0"><?= e($f['step3_title'] ?? '') ?></legend>
          <div class="fields">
            <label class="field"><span>Nom et prénom *</span><input name="nom" maxlength="120" autocomplete="name" required></label>
            <div class="fields--2">
              <label class="field"><span>Téléphone *</span><input name="tel" type="tel" maxlength="30" autocomplete="tel" value="<?= e($preTel) ?>" required></label>
              <label class="field"><span>Email</span><input name="email" type="email" maxlength="160" autocomplete="email"></label>
            </div>
            <label class="field"><span>Précisions (facultatif)</span><textarea name="msg" rows="3" maxlength="2000"></textarea></label>
          </div>
        </fieldset>

        <div class="dv__nav">
          <button class="btn btn-l dv__back" type="button" data-dv="back"<?= $step === 1 ? ' hidden' : '' ?>><span class="fx"></span><span>Retour</span></button>
          <button class="dv__next" type="submit" data-dv="next"><?= $step === 3 ? 'Envoyer ma demande' : 'Continuer →' ?></button>
        </div>
        <div class="dv__err" id="rv-devis-err" hidden></div>
        <div class="dv__legal"><?= e($f['legal'] ?? '') ?></div>
      </form>

      <div class="dv__ok" id="rv-devis-ok"<?= $sent ? '' : ' hidden' ?>>
        <div class="dv__okc">✓</div>
        <div class="dv__okt"><?= e($pg['confirm_title'] ?? '') ?></div>
        <p class="dv__okp"><?= e($pg['confirm_text'] ?? '') ?></p>
        <a class="dv__okl" href="tel:<?= e(tel($site['phone'])) ?>">Ou appelez maintenant : <?= e($site['phone']) ?></a>
      </div>
    </div>

    <div class="dv__side" data-reveal="right" data-reveal-delay="120">
      <div class="dv__inc">
        <div class="dv__inct"><?= e($pg['includes_title'] ?? '') ?></div>
        <div class="dv__incl">
          <?php foreach (($pg['includes'] ?? []) as $d): ?><div><b>✓</b><span><?= e($d) ?></span></div><?php endforeach; ?>
        </div>
      </div>
      <div class="dv__talk">
        <div class="dv__talkt"><?= e($pg['talk_title'] ?? '') ?></div>
        <a class="dv__tel" href="tel:<?= e(tel($site['phone'])) ?>"><?= e($site['phone']) ?></a>
        <div class="dv__talks"><?= e($site['hours']) ?> · Service technique <?= e($site['sav_phone']) ?></div>
      </div>
    </div>
  </div>
</div>
