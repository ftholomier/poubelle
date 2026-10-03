<?php
/** Contribuer (maquette « Contribuer ») : 4 étapes, fichiers, droits. Variables : $types, $flash, $ticket, $fiche */
$old = $flash['old'] ?? [];
$o = fn (string $k) => (string) ($old[$k] ?? '');
$after = [
    ['01', t('Vous proposez'), t('Correction, photo, document ou témoignage.')],
    ['02', t("L'équipe vérifie"), t('Chaque envoi rejoint la file de validation du back-office.')],
    ['03', t('On publie'), t('Avec votre crédit, sur la fiche concernée.')],
    ['04', t('On vous prévient'), t('Un e-mail dès la mise en ligne.')],
];
?>
<section class="wrap cgrid" id="formulaire">
  <div class="stack" style="gap:22px">
    <span class="eyebrow eyebrow--lg"><?= e(t('Contribuer au musée')) ?></span>
    <h1 class="h-xl cbig"><?= e(t('Les archives')) ?><br><?= e(t('dorment dans')) ?><br><span class="blue"><?= e(t('vos greniers.')) ?></span></h1>
    <p class="lead" style="max-width:42ch"><?= e(t("Une correction, une photo de famille au stade, un billet, un fanion : proposez-le. Chaque envoi est relu par l'équipe avant publication, avec votre nom en crédit.")) ?></p>
    <div class="cafter">
      <?php foreach ($after as [$n, $tt, $d]): ?><div><b><?= e($n) ?></b><span><strong><?= e($tt) ?></strong><small><?= e($d) ?></small></span></div><?php endforeach; ?>
    </div>
  </div>
  <?php if ($ticket): ?>
    <div class="cwizard cwizard--done">
      <div class="cwizard__body cthanks">
        <img src="/assets/img/logo-sochaux-retro.png" alt="" width="106" height="120" class="is-in">
        <b><?= e(t('Merci, Lionceau !')) ?></b>
        <p><?= e(t('Votre contribution est dans la file de validation. Vous recevrez un e-mail dès sa publication.')) ?></p>
        <span class="cticket"><?= e(t('N° de suivi')) ?> · <?= e($ticket) ?></span>
        <a class="cform__send" href="<?= e(url('/contribuer/')) ?>#formulaire"><?= e(t('Proposer autre chose')) ?></a>
      </div>
    </div>
  <?php else: ?>
  <form class="cwizard" method="post" action="<?= e(url('/contribuer/')) ?>" enctype="multipart/form-data" data-protect data-wizard>
    <?= csrf_field() ?>
    <input type="hidden" name="_ts" value="<?= e(form_ts()) ?>">
    <div class="hp" aria-hidden="true"><label>Site web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="cwizard__steps" data-wz-steps>
      <?php foreach ([t('Type'), t('Détails'), t('Droits'), t('Envoi')] as $i => $label): ?>
        <button type="button" data-wz-go="<?= $i ?>"<?= $i === 0 ? ' class="is-on"' : '' ?>><?= $i + 1 ?> · <?= e($label) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="cwizard__body">
      <?php if ($flash): ?><p class="alert alert--error" role="alert"><?= e($flash['msg']) ?></p><?php endif; ?>
      <fieldset class="wz" data-wz="0">
        <legend class="wz__t"><?= e(t('Que souhaitez-vous proposer ?')) ?></legend>
        <div class="ctypes">
          <?php foreach ($types as $k => [$icon, $label, $d]): ?>
            <label class="ctype"><input type="radio" name="type" value="<?= e($k) ?>"<?= $o('type') === $k ? ' checked' : '' ?> required><span class="ctype__i" aria-hidden="true"><?= e($icon) ?></span><span class="ctype__l"><?= e(t($label)) ?></span><span class="ctype__d"><?= e(t($d)) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <fieldset class="wz" data-wz="1">
        <legend class="wz__t"><?= e(t('Les détails')) ?></legend>
        <label class="cfield"><span><?= e(t('Fiche concernée (facultatif)')) ?></span><input name="fiche" maxlength="300" placeholder="<?= e(t('Un match, un joueur, une saison…')) ?>" value="<?= e($o('fiche') ?: $fiche) ?>"></label>
        <div class="cfield2">
          <label class="cfield"><span><?= e(t('Date (même approximative)')) ?></span><input name="date" maxlength="120" placeholder="<?= e(t('ex. années 80')) ?>" value="<?= e($o('date')) ?>"></label>
          <label class="cfield"><span><?= e(t('Lieu')) ?></span><input name="place" maxlength="160" placeholder="<?= e(t('ex. stade Bonal')) ?>" value="<?= e($o('place')) ?>"></label>
        </div>
        <label class="cfield"><span><?= e(t('Description')) ?></span><textarea name="description" rows="5" maxlength="10000" placeholder="<?= e(t("Qui, quoi, l'histoire derrière…")) ?>"><?= e($o('description')) ?></textarea></label>
        <label class="cdrop" data-drop>
          <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.tif,.tiff,.pdf" data-files>
          <b><?= e(t('Glissez vos fichiers ici')) ?></b>
          <small><?= e(t('JPG, PNG, TIFF, PDF · 25 Mo max par fichier · scans en haute définition bienvenus')) ?></small>
        </label>
        <div class="cfiles" data-file-list></div>
      </fieldset>
      <fieldset class="wz" data-wz="2">
        <legend class="wz__t"><?= e(t('Vous et vos droits')) ?></legend>
        <div class="cfield2">
          <label class="cfield"><span><?= e(t('Nom')) ?></span><input name="name" maxlength="120" autocomplete="name" placeholder="<?= e(t('Prénom Nom')) ?>" value="<?= e($o('name')) ?>" required></label>
          <label class="cfield"><span><?= e(t('E-mail')) ?></span><input name="email" type="email" maxlength="160" autocomplete="email" placeholder="vous@exemple.fr" value="<?= e($o('email')) ?>" required></label>
        </div>
        <label class="cfield"><span><?= e(t('Crédit affiché')) ?></span><input name="credit" maxlength="200" placeholder="<?= e(t('ex. Collection famille Martin')) ?>" value="<?= e($o('credit')) ?>"></label>
        <label class="crights"><input type="checkbox" name="rights" value="1" required><span class="crights__box" aria-hidden="true"></span><span><?= e(t("Je suis l'auteur ou le détenteur des droits de ces documents, et j'autorise Sochaux Rétro à les publier gratuitement sur le musée en ligne, avec mention du crédit ci-dessus.")) ?></span></label>
      </fieldset>
      <div class="cwizard__nav">
        <button type="button" class="cbtn-ghost" data-wz-prev><?= e(t('← Retour')) ?></button>
        <button type="button" class="cform__send" data-wz-next><?= e(t('Continuer →')) ?></button>
        <button type="submit" class="cform__send" data-wz-submit><?= e(t('Envoyer ma contribution')) ?></button>
      </div>
    </div>
  </form>
  <?php endif; ?>
</section>
