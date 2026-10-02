<?php
/** Contact & partenaires (maquette « Contact »). Variables : $reason, $partners, $flash, $old */
use App\Front\Community;

$o = fn (string $k) => (string) ($old[$k] ?? '');
?>
<section class="wrap cgrid" id="formulaire">
  <div class="stack" style="gap:22px">
    <span class="eyebrow eyebrow--lg"><?= e(t('Contact & partenaires')) ?></span>
    <h1 class="h-xl cbig"><?= e(t('Parlons')) ?><br><span class="blue"><?= e(t('jaune et bleu.')) ?></span></h1>
    <p class="lead" style="max-width:42ch"><?= e(t('Un partenariat, une archive à confier, une erreur à signaler ? On vous répond dans les plus brefs délais.')) ?></p>
    <div class="creasons" role="tablist" aria-label="<?= e(t('Objet de votre demande')) ?>">
      <?php foreach (Community::REASONS as $k => $label): ?>
        <a class="creason<?= $k === $reason ? ' is-on' : '' ?>" href="<?= e(url('/contact/') . '?objet=' . $k) ?>#formulaire" data-reason="<?= e($k) ?>" role="tab" aria-selected="<?= $k === $reason ? 'true' : 'false' ?>"><span><?= e(t($label)) ?></span><b aria-hidden="true"><?= $k === $reason ? '●' : '→' ?></b></a>
      <?php endforeach; ?>
    </div>
  </div>
  <form class="cform" method="post" action="<?= e(url('/contact/')) ?>" data-protect data-contact-form>
    <?= csrf_field() ?>
    <input type="hidden" name="_ts" value="">
    <input type="hidden" name="reason" value="<?= e($reason) ?>" data-reason-input>
    <div class="hp" aria-hidden="true"><label>Site web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <span class="cform__obj"><?= e(t('Objet')) ?> : <b data-reason-label><?= e(t(Community::REASONS[$reason])) ?></b></span>
    <?php if ($flash): ?><p class="alert alert--<?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= e($flash['msg']) ?></p><?php endif; ?>
    <label class="cfield"><span><?= e(t('Nom')) ?></span><input name="name" required maxlength="120" autocomplete="name" placeholder="<?= e(t('Prénom Nom')) ?>" value="<?= e($o('name')) ?>"></label>
    <label class="cfield"><span><?= e(t('E-mail')) ?></span><input name="email" type="email" required maxlength="160" autocomplete="email" placeholder="vous@exemple.fr" value="<?= e($o('email')) ?>"></label>
    <label class="cfield" data-only="partenariat"<?= $reason !== 'partenariat' ? ' hidden' : '' ?>><span><?= e(t('Entreprise')) ?></span><input name="org" maxlength="160" autocomplete="organization" placeholder="<?= e(t('Nom de la structure')) ?>" value="<?= e($o('org')) ?>"></label>
    <label class="cfield" data-only="erreur"<?= $reason !== 'erreur' ? ' hidden' : '' ?>><span><?= e(t('Page concernée')) ?></span><input name="page" maxlength="300" placeholder="<?= e(t('Adresse ou titre de la fiche')) ?>" value="<?= e($o('page')) ?>"></label>
    <label class="cfield" data-not="partenariat"<?= $reason === 'partenariat' ? ' hidden' : '' ?>><span><?= e(t('Téléphone (facultatif)')) ?></span><input name="phone" maxlength="40" autocomplete="tel" placeholder="06 …" value="<?= e($o('phone')) ?>"></label>
    <label class="cfield"><span><?= e(t('Message')) ?></span><textarea name="message" rows="6" required maxlength="8000" placeholder="<?= e(t('Votre message…')) ?>"><?= e($o('message')) ?></textarea></label>
    <label class="ccheck"><input type="checkbox" name="consent" value="1" required> <span><?= e(t('J’accepte que Sochaux Rétro utilise ces informations pour me répondre. Elles ne sont jamais cédées.')) ?> <a href="<?= e(url('/confidentialite/')) ?>"><?= e(t('En savoir plus')) ?></a></span></label>
    <button type="submit" class="cform__send"><?= e(t('Envoyer')) ?></button>
  </form>
</section>
<section class="cpartners">
  <div class="wrap stack" style="gap:28px">
    <h2 class="h-2"><?= e(t('Ils soutiennent le musée')) ?></h2>
    <?php if ($partners): ?>
    <div class="cpartners__grid">
      <?php foreach ($partners as $p): ?>
        <<?= !empty($p['url']) ? 'a href="' . e($p['url']) . '" target="_blank" rel="noopener"' : 'div' ?> class="cpartner" title="<?= e($p['name'] ?? '') ?>">
          <?php if (!empty($p['logo'])): ?><img src="<?= e(img($p['logo'], 480)) ?>" alt="<?= e($p['name'] ?? '') ?>" loading="lazy"><?php else: ?><span><?= e($p['name'] ?? '') ?></span><?php endif; ?>
        </<?= !empty($p['url']) ? 'a' : 'div' ?>>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <p class="lead" style="max-width:none"><?= e(t('Vous souhaitez associer votre entreprise à la mémoire du FCSM ? Choisissez « Devenir partenaire » ci-dessus.')) ?></p>
    <?php endif; ?>
  </div>
</section>
